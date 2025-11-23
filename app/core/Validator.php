<?php
// FILE: /app/core/Validator.php

/**
 * Input Validation
 *
 * Validates input data against defined rules.
 */
class Validator {

    private $errors = [];

    /**
     * Validate data against rules
     *
     * @param array $data
     * @param array $rules Format: ['field' => 'required|email|min:3']
     * @return array Errors array
     */
    public function validate($data, $rules) {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = isset($data[$field]) ? $data[$field] : null;

            foreach ($ruleList as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        return $this->errors;
    }

    /**
     * Apply single validation rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param array $data
     */
    private function applyRule($field, $value, $rule, $data) {
        // Parse rule with parameters
        $params = [];
        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, ucfirst($field) . ' is required');
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid email');
                }
                break;

            case 'min':
                $min = (int) $params[0];
                if ($value && strlen($value) < $min) {
                    $this->addError($field, ucfirst($field) . " must be at least {$min} characters");
                }
                break;

            case 'max':
                $max = (int) $params[0];
                if ($value && strlen($value) > $max) {
                    $this->addError($field, ucfirst($field) . " must not exceed {$max} characters");
                }
                break;

            case 'numeric':
                if ($value && !is_numeric($value)) {
                    $this->addError($field, ucfirst($field) . ' must be numeric');
                }
                break;

            case 'alpha':
                if ($value && !ctype_alpha($value)) {
                    $this->addError($field, ucfirst($field) . ' must contain only letters');
                }
                break;

            case 'alphanumeric':
                if ($value && !ctype_alnum($value)) {
                    $this->addError($field, ucfirst($field) . ' must contain only letters and numbers');
                }
                break;

            case 'in':
                if ($value && !in_array($value, $params)) {
                    $this->addError($field, ucfirst($field) . ' must be one of: ' . implode(', ', $params));
                }
                break;

            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid URL');
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value && (!isset($data[$confirmField]) || $value !== $data[$confirmField])) {
                    $this->addError($field, ucfirst($field) . ' confirmation does not match');
                }
                break;

            case 'unique':
                // Format: unique:table,column,exceptId
                if ($value) {
                    $table = $params[0];
                    $column = isset($params[1]) ? $params[1] : $field;
                    $exceptId = isset($params[2]) ? $params[2] : null;

                    if ($this->checkUnique($table, $column, $value, $exceptId)) {
                        $this->addError($field, ucfirst($field) . ' already exists');
                    }
                }
                break;
        }
    }

    /**
     * Check if value is unique in database
     *
     * @param string $table
     * @param string $column
     * @param mixed $value
     * @param int|null $exceptId
     * @return bool
     */
    private function checkUnique($table, $column, $value, $exceptId = null) {
        $db = Database::getInstance()->getConnection();

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
        $params = [$value];

        if ($exceptId) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Add validation error
     *
     * @param string $field
     * @param string $message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get all errors
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     *
     * @return bool
     */
    public function hasErrors() {
        return count($this->errors) > 0;
    }
}
