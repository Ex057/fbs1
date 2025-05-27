<?php
require_once 'dhis2_shared.php';
require_once 'dhis2_post_function.php';

class DHIS2SubmissionHandler {
    private $conn;
    private $key;
    private $programUID;
    private $fieldMappingCache = [];

    public function __construct(mysqli $conn, string $key = 'UiO', string $programUID = 'GfDOw2s4mCj') {
        $this->conn = $conn;
        $this->instance = $key;
        $this->programUID = $programUID;
        $this->loadFieldMappings();
    }

    private function loadFieldMappings(): void {
        $stmt = $this->conn->prepare("SELECT field_name, dhis2_dataelement_id, dhis2_option_set_id FROM dhis2_system_field_mapping");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $this->fieldMappingCache[$row['field_name']] = [
                'data_element' => $row['dhis2_dataelement_id'],
                'option_set' => $row['dhis2_option_set_id'],
            ];
        }
    }

    private function getMappedUID(string $fieldName, string $type = 'data_element'): ?string {
        return $this->fieldMappingCache[$fieldName][$type] ?? null;
    }

    public function processSubmission(int $submissionId): array {
        try {
            // Check if this submission was already sent successfully
            if ($this->isAlreadySubmitted($submissionId)) {
                return ['success' => true, 'message' => 'Submission was already processed successfully'];
            }

            // Get submission data with explicit JOINs to ensure we have ownership and service unit info
            $submissionData = $this->getSubmissionData($submissionId);
            if (!$submissionData) {
                throw new Exception("Submission not found");
            }

            // Get all question responses
            $responses = $this->getSubmissionResponses($submissionId);

            // Generate a unique event ID to prevent duplication
            $eventUID = $this->generateEventUID($submissionId, $submissionData);

            // Prepare and submit payload
            $payload = $this->prepareDHIS2Payload($submissionData, $responses, $eventUID);

            // Log complete payload for debugging
            error_log("DHIS2 Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

            $result = $this->submitToDHIS2($payload);

            // If successful, mark this submission as processed
            if ($result['success']) {
                $this->markAsSubmitted($submissionId);
            }

            return $result;

        } catch (Exception $e) {
            error_log("DHIS2 Submission Error: " . $e->getMessage());
            return ['success' => false, 'message' => "Final submission error: " . $e->getMessage()];
        }
    }

    private function isAlreadySubmitted(int $submissionId): bool {
        $stmt = $this->conn->prepare("
            SELECT id FROM dhis2_submission_log
            WHERE submission_id = ? AND status = 'SUCCESS'
        ");

        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    private function markAsSubmitted(int $submissionId): void {
        $stmt = $this->conn->prepare("
            INSERT INTO dhis2_submission_log (submission_id, status, submitted_at)
            VALUES (?, 'SUCCESS', NOW())
            ON DUPLICATE KEY UPDATE status = 'SUCCESS', submitted_at = NOW()
        ");

        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
    }

    private function generateEventUID(int $submissionId, array $submissionData): string {
        $uniqueFields = [
            $submissionId,
            $submissionData['location_uid'] ?? '',
            $submissionData['period'] ?? '',
            $this->programUID
        ];

        $baseString = implode('-', $uniqueFields);
        $hash = substr(md5($baseString), 0, 11);

        return $hash;
    }

    private function getSubmissionData(int $submissionId): ?array {
        $stmt = $this->conn->prepare("
            SELECT
                s.*,
                l.uid as location_uid,
                o.id as ownership_id,
                o.name as ownership_name,
                su.id as service_unit_id,
                su.name as service_unit_name
            FROM submission s
            LEFT JOIN location l ON s.location_id = l.id
            LEFT JOIN owner o ON s.ownership_id = o.id
            LEFT JOIN service_unit su ON s.service_unit_id = su.id
            WHERE s.id = ?
        ");

        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if ($data) {
            error_log("Submission data: " . json_encode($data, JSON_PRETTY_PRINT));
        }

        return $data;
    }

    private function getSubmissionResponses(int $submissionId): array {
        $responses = [];
        $stmt = $this->conn->prepare("
            SELECT sr.question_id, sr.response_value
            FROM submission_response sr
            WHERE sr.submission_id = ?
        ");

        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $responses[$row['question_id']] = [
                'value' => $row['response_value']
            ];
        }

        if (!empty($responses)) {
            error_log("Submission responses: " . json_encode($responses, JSON_PRETTY_PRINT));
        }

        return $responses;
    }

    private function prepareDHIS2Payload(array $submissionData, array $responses, string $eventUID): array {
        $eventDate = $submissionData['period'] ?? date('Y-m-d');
        $dataValues = [];

        // 1. Add ownership data element
        $ownershipDE = $this->getMappedUID('ownership');
        $ownershipOS = $this->getMappedUID('ownership', 'option_set');
        if (!empty($submissionData['ownership_name']) && $ownershipDE) {
            $ownershipCode = $this->getOptionCode($submissionData['ownership_name'], $ownershipOS);
            if ($ownershipCode) {
                $dataValues[] = [
                    'dataElement' => $ownershipDE,
                    'value' => $ownershipCode
                ];
                error_log("Added ownership: {$submissionData['ownership_name']} -> $ownershipCode (DE: $ownershipDE, OS: $ownershipOS)");
            } else {
                error_log("WARNING: Could not map ownership value: " . $submissionData['ownership_name'] . " (OS: $ownershipOS)");
            }
        }

        // 2. Add service unit data element
        $serviceUnitDE = $this->getMappedUID('service_unit');
        $serviceUnitOS = $this->getMappedUID('service_unit', 'option_set');
        if (!empty($submissionData['service_unit_name']) && $serviceUnitDE) {
            $serviceUnitCode = $this->getOptionCode($submissionData['service_unit_name'], $serviceUnitOS);
            if ($serviceUnitCode) {
                $dataValues[] = [
                    'dataElement' => $serviceUnitDE,
                    'value' => $serviceUnitCode
                ];
                error_log("Added service unit: {$submissionData['service_unit_name']} -> $serviceUnitCode (DE: $serviceUnitDE, OS: $serviceUnitOS)");
            } else {
                error_log("WARNING: Could not map service unit value: " . $submissionData['service_unit_name'] . " (OS: $serviceUnitOS)");
            }
        }

        // 3. Add other standard fields
        if (!empty($submissionData['age'])) {
            if ($ageDE = $this->getMappedUID('age')) {
                $dataValues[] = [
                    'dataElement' => $ageDE,
                    'value' => (string)$submissionData['age']
                ];
            }
        }

        if (!empty($submissionData['sex'])) {
            if ($sexDE = $this->getMappedUID('sex')) {
                $sexOS = $this->getMappedUID('sex', 'option_set');
                $sexCode = $this->getOptionCode($submissionData['sex'], $sexOS);
                if ($sexCode) {
                    $dataValues[] = [
                        'dataElement' => $sexDE,
                        'value' => $sexCode
                    ];
                }
            }
        }

        if (!empty($submissionData['period'])) {
            if ($periodDE = $this->getMappedUID('period')) {
                $dataValues[] = [
                    'dataElement' => $periodDE,
                    'value' => $submissionData['period']
                ];
            }
        }

        // 4. Add question responses - use DIRECT database query to get mappings
        if (!empty($responses)) {
            $questionIds = array_keys($responses);
            $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

            $stmt = $this->conn->prepare("
                SELECT qm.question_id, qm.dhis2_dataelement_id, qm.dhis2_option_set_id
                FROM question_dhis2_mapping qm
                WHERE qm.question_id IN ($placeholders)
            ");

            // Dynamically create parameter binding
            $bindTypes = str_repeat('i', count($questionIds));
            $stmt->bind_param($bindTypes, ...$questionIds);
            $stmt->execute();
            $result = $stmt->get_result();

            $questionMappings = [];
            while ($row = $result->fetch_assoc()) {
                $questionMappings[$row['question_id']] = $row;
            }

            // Log what mappings we found
            error_log("Question mappings: " . json_encode($questionMappings, JSON_PRETTY_PRINT));

            // Process each response
            foreach ($responses as $questionId => $responseData) {
                $responseValue = $responseData['value'];

                if (isset($questionMappings[$questionId])) {
                    $mapping = $questionMappings[$questionId];
                    $value = $responseValue;

                    // Skip empty responses
                    if (empty($value)) continue;

                    // Handle option set questions
                    if (!empty($mapping['dhis2_option_set_id'])) {
                        $optionCode = $this->getOptionCode($value, $mapping['dhis2_option_set_id']);
                        if ($optionCode) {
                            $value = $optionCode;
                        } else {
                            error_log("WARNING: No option mapping for question $questionId value: $value (OS: {$mapping['dhis2_option_set_id']})");
                            continue; // Skip if no valid mapping
                        }
                    }

                    $dataValues[] = [
                        'dataElement' => $mapping['dhis2_dataelement_id'],
                        'value' => (string)$value
                    ];

                    error_log("Added question $questionId response: $value -> data element: {$mapping['dhis2_dataelement_id']} (OS: {$mapping['dhis2_option_set_id']})");
                } else {
                    error_log("WARNING: No DHIS2 mapping for question ID: $questionId");
                }
            }
        }

        // Create the final payload with proper structure and include event UID
        return [
            'events' => [
                [
                    'event' => $eventUID, // Set explicit event UID
                    'orgUnit' => $submissionData['location_uid'],
                    'program' => $this->programUID,
                    'eventDate' => $eventDate,
                    'occurredAt' => $eventDate,
                    'status' => 'COMPLETED',
                    'dataValues' => $dataValues
                ]
            ]
        ];
    }

    private function getOptionCode(string $localValue, ?string $optionSetId): ?string {
        if (empty($optionSetId)) {
            return $localValue; // No option set to map to
        }
        $stmt = $this->conn->prepare("
            SELECT dhis2_option_code
            FROM dhis2_option_set_mapping
            WHERE local_value = ? AND dhis2_option_set_id = ?
        ");

        $stmt->bind_param("ss", $localValue, $optionSetId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        // Log the lookup attempt
        if (!$row) {
            error_log("Option lookup failed: Local value '$localValue' not found in option set '$optionSetId'");

            // Try a case-insensitive lookup as fallback
            $stmt = $this->conn->prepare("
                SELECT dhis2_option_code
                FROM dhis2_option_set_mapping
                WHERE LOWER(local_value) = LOWER(?) AND dhis2_option_set_id = ?
            ");
            $stmt->bind_param("ss", $localValue, $optionSetId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if (!$row) {
                return null;
            }
        }

        return $row['dhis2_option_code'];
    }

    private function submitToDHIS2(array $payload): array {
        try {
            // Make single API call - no retries to prevent duplicates
            $response = dhis2_post('/api/events', $payload, $this->instance);

            if ($response === null) {
                throw new Exception("DHIS2 API returned null response");
            }

            // Log the full response
            error_log("DHIS2 Response: " . json_encode($response, JSON_PRETTY_PRINT));

            // Check for success
            if (isset($response['status']) && $response['status'] === 'SUCCESS') {
                return ['success' => true, 'message' => 'Successfully submitted to DHIS2'];
            }

            // Handle specific DHIS2 errors
            if (isset($response['response'])) {
                $status = $response['response']['status'] ?? '';
                $description = $response['response']['description'] ?? '';

                if ($status === 'ERROR') {
                    // Check for duplicate event
                    if (strpos($description, 'already exists') !== false) {
                        return ['success' => true, 'message' => 'Data was already submitted to DHIS2'];
                    }

                    // Check for validation errors
                    if (strpos($description, 'Validation failed') !== false) {
                        throw new Exception("DHIS2 validation failed: " . $description);
                    }
                }
            }

            throw new Exception($response['message'] ?? json_encode($response));

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            // Still treat duplicate errors as success
            if (strpos($errorMsg, 'already exists') !== false) {
                return ['success' => true, 'message' => $errorMsg];
            }
            throw $e;
        }
    }
}