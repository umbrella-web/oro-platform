<?php

namespace Oro\Bundle\FilterBundle\Filter;

use Oro\Bundle\FilterBundle\Form\Type\Filter\TextFilterType;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;

class StringFilter extends AbstractFilter
{
    /**
     * {@inheritDoc}
     */
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        $data = $this->parseData($data);
        if (!$data) {
            return false;
        }

        $type = $data['type'];

        $parameterName = $ds->generateParameterName($this->getName());

        $this->applyFilterToClause(
            $ds,
            $this->buildComparisonExpr(
                $ds,
                $type,
                $this->get(FilterUtility::DATA_NAME_KEY),
                $parameterName
            )
        );

        if (!in_array($type, [FilterUtility::TYPE_EMPTY, FilterUtility::TYPE_NOT_EMPTY])) {
            $ds->setParameter($parameterName, $data['value']);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function getFormType()
    {
        return TextFilterType::NAME;
    }

    /**
     * @param mixed $data
     *
     * @return array|bool
     */
    protected function parseData($data)
    {
        $type = isset($data['type']) ? $data['type'] : null;
        if (!in_array($type, [FilterUtility::TYPE_EMPTY, FilterUtility::TYPE_NOT_EMPTY])
            && (!is_array($data) || !array_key_exists('value', $data) || empty($data['value']))
        ) {
            return false;
        }

        $data['type'] = $type;
        $data['value'] = $this->parseValue($data['type'], $data['value']);

        return $data;
    }

    /**
     * Build an expression used to filter data
     *
     * @param FilterDatasourceAdapterInterface $ds
     * @param int                              $comparisonType
     * @param string                           $fieldName
     * @param string                           $parameterName
     * @return string
     */
    protected function buildComparisonExpr(
        FilterDatasourceAdapterInterface $ds,
        $comparisonType,
        $fieldName,
        $parameterName
    ) {
        switch ($comparisonType) {
            case TextFilterType::TYPE_EQUAL:
                return $ds->expr()->eq($fieldName, $parameterName, true);
            case TextFilterType::TYPE_NOT_CONTAINS:
                return $ds->expr()->notLike($fieldName, $parameterName, true);
            case TextFilterType::TYPE_IN:
                return $ds->expr()->in($fieldName, $parameterName, true);
            case TextFilterType::TYPE_NOT_IN:
                return $ds->expr()->notIn($fieldName, $parameterName, true);
            case FilterUtility::TYPE_EMPTY:
                return $ds->expr()->isNull($fieldName);
            case FilterUtility::TYPE_NOT_EMPTY:
                return $ds->expr()->isNotNull($fieldName);
            default:
                return $ds->expr()->like($fieldName, $parameterName, true);
        }
    }

    /**
     * Return a value depending on comparison type
     *
     * @param int    $comparisonType
     * @param string $value
     *
     * @return mixed
     */
    protected function parseValue($comparisonType, $value)
    {
        switch ($comparisonType) {
            case TextFilterType::TYPE_CONTAINS:
            case TextFilterType::TYPE_NOT_CONTAINS:
                return sprintf('%%%s%%', $value);
            case TextFilterType::TYPE_STARTS_WITH:
                return sprintf('%s%%', $value);
            case TextFilterType::TYPE_ENDS_WITH:
                return sprintf('%%%s', $value);
            case TextFilterType::TYPE_IN:
            case TextFilterType::TYPE_NOT_IN:
                return array_map('trim', explode(',', $value));
            default:
                return $value;
        }
    }
}
