<?php
/**
* Google style search
*  - Wrap parameters in square brackets if is more than one word
*  - All non parameter text is gathered into keyword variable
*/

namespace Edge\Search;

use Zend\Stdlib\ArrayUtils;

class Filter
{
    const PARAM_SORT  = 'sort';
    const PARAM_ORDER = 'order';

    const ORDER_DESC = 'desc';
    const ORDER_ASC  = 'asc';

    const COMPARISON_EQUALS         = ':';
    const COMPARISON_NOT_EQUALS     = '!';
    const COMPARISON_LIKE           = '~';
    const COMPARISON_NOT_LIKE       = '!~';
    const COMPARISON_GREATER        = '>';
    const COMPARISON_LESS           = '<';
    const COMPARISON_GREATER_OR_EQ  = '>=';
    const COMPARISON_LESS_OR_EQ     = '<=';

    const FILTER_REGEX = '/([a-zA-Z-\.]+)(:|!~|~|!|>=|>|<=|<)((?:\[[^)]+?\]|[^\[\]\s]+))/';
    const GROUP_REGEX  = '/(?:\(([^()]*)\))/';

    /**
     * Allowed search fields
     *
     * @var array
     */
    protected $searchFields = [];

    protected $data = [];

    protected $keywords = null;

    protected $sort = null;

    protected $order = self::ORDER_DESC;


    protected function extract($query)
    {
        if (empty($query)) {
            return $this;
        }

        $this->extractQueryPart(preg_replace(self::GROUP_REGEX, '', $query));

        $groups = [];
        preg_match_all(self::GROUP_REGEX, $query, $groups);
        foreach ($groups[1] as $key => $part) {
            $this->extractQueryPart($part, $key + 1);
        }

        return $this;
    }

    protected function extractQueryPart($part, $group = 0)
    {
        $params = [];
        preg_match_all(self::FILTER_REGEX, $part, $params);

        foreach ($params[1] as $key => $field) {
            $value = $this->processValue($params[3][$key]);
            $this->addFieldValue($field, $value, $params[2][$key], false, $group);
        }

        if ($group == 0) {
            $keywords = trim(preg_replace(self::FILTER_REGEX, '', $part));
            if ($keywords !== '') {
                $this->setKeywords($keywords);
            }
        }
    }

    private function processValue($value)
    {
        $value = str_replace(array('[', ']'), '', $value);

        if ($value == 'null') {
            return null;
        }

        return $value;
    }

    private function unprocessValue($value)
    {
        if (strstr($value, ' ')) {
             $value = '[' . $value . ']';
        } elseif ($value === false) {
            $value = '0';
        } elseif ($value === null) {
            $value = 'null';
        }

        return $value;
    }

    /**
     * Add a value to the filter
     *
     * @param string $field
     * @param string $value
     * @param string $comparison see class consts
     * @param boolean $default whether the value should be considered a default
     * @param boolean $group which OR group to apply value to [optional]
     * @return Filter
     */
    public function addFieldValue($field, $value, $comparison = self::COMPARISON_EQUALS, $default = false, $group = 0)
    {
        if ($this->hasSearchField($field)) {
            if (isset($this->data[$group][$field]) && !$default) {
                foreach ($this->data[$group][$field] as $key => $values) {
                    if ($values['default']) {
                        unset($this->data[$group][$field][$key]);
                    }
                }
            }

            $this->data[$group][$field][] = array(
                'value'      => $value,
                'comparison' => $comparison,
                'default'    => $default,
            );

            return $this;
        }

        if ($field == self::PARAM_SORT) {
            if ($this->hasSearchField($value)) {
                $this->sort = $value;
            }
            return $this;
        }

        if ($field == self::PARAM_ORDER) {
            if ($value != self::ORDER_DESC) {
                $this->order = self::ORDER_ASC;
            } else {
                $this->order = self::ORDER_DESC;
            }
            return $this;
        }

        return $this;
    }

    /**
     * Get all field values, wrapped into groups.
     * Each field contains an array of values
     *
     * @return array
     */
    public function getAllFieldValues()
    {
        return $this->data;
    }

    /**
     * Get an array of values for a specific field
     *
     * @param string $field
     * @param int    $group
     * @return array
     */
    public function getFieldValues($field, $group = 0)
    {
        if (isset($this->data[$group][$field])) {
            return $this->data[$group][$field];
        }
        return [];
    }

    /**
     * Set the query string to be extracted
     *
     * @param string $query
     * @return \Edge\Search\Filter
     */
    public function setQueryString($query)
    {
        $this->extract($query);
        return $this;
    }

    /**
     * Get the resulting query string
     *
     * @todo handle all use cases, including square brackets
     *
     * @return string
     */
    public function getQueryString()
    {
        $filterStr = '';

        if (empty($this->data)) {
            return $this->getKeywords();
        }

        foreach ($this->data as $group => $fields) {
            $groupStr = '';
            foreach ($fields as $field => $values) {
                foreach ($values as $value) {
                    $groupStr.= $field . $value['comparison'] . $this->unprocessValue($value['value']) . ' ';
                }
            }
            if ($group > 0) {
                $groupStr = '(' . trim($groupStr) . ') ';
            }
            $filterStr.= $groupStr;
        }

        if (null !== $this->sort) {
            $filterStr.= self::PARAM_SORT . self::COMPARISON_EQUALS . $this->sort . ' ' . self::PARAM_ORDER . self::COMPARISON_EQUALS . $this->order . ' ';
        }

        $filterStr.= $this->getKeywords();

        return trim($filterStr);
    }

    public function getKeywords()
    {
        return $this->keywords;
    }

    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function getSortField()
    {
        return $this->sort;
    }

    public function getSortOrder()
    {
        return $this->order;
    }

    public function setSort($sort, $order = self::ORDER_DESC)
    {
        if (!$this->hasSearchField($sort)) {
            throw new Exception\InvalidArgumentException("Invalid sort field [$sort] specified");
        }

        if ($order !== self::ORDER_DESC) {
            $order = self::ORDER_ASC;
        }

        $this->sort  = $sort;
        $this->order = $order;

        return $this;
    }

    public function clearSort()
    {
        $this->sort = null;
    }

    public function setSearchFields(array $fields)
    {
        if (ArrayUtils::isList($fields)) {
            $this->searchFields = array_flip($fields);
        } else {
            $this->searchFields = $fields;
        }
        return $this;
    }

    public function getAllSearchFields()
    {
        return $this->searchFields;
    }

    public function hasSearchField($field)
    {
        return isset($this->searchFields[$field]);
    }

    public function getSearchField($field)
    {
        if (!$this->hasSearchField($field)) {
            throw new Exception\InvalidArgumentException("Invalid field [$field] specified");
        }

        return $this->searchFields[$field];
    }

    public function setDefaultValues(array $values)
    {
        foreach ($values as $field => $value) {
            if (is_array($value)) {
                $value = $value['value'];
                $comparison = isset($value['comparison']) ? $value['comparison'] : self::COMPARISON_EQUALS;
            } else {
                $comparison = self::COMPARISON_EQUALS;
            }
            $this->addFieldValue($field, $value, $comparison, true);
        }

        return $this;
    }

    public function clear()
    {
        $this->data = [];
        return $this;
    }
}
