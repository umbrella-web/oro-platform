<?php

namespace Oro\Bundle\DashboardBundle\Provider\Converters;

use Oro\Bundle\FilterBundle\Form\Type\Filter\AbstractDateFilterType;

class PreviousFilterDateRangeConverter extends FilterDateTimeRangeConverter
{
    /**
     * {@inheritdoc}
     */
    public function getConvertedValue(array $widgetConfig, $value = null, array $config = [], array $options = [])
    {
        $result = [];

        if (($value === null && $config['converter_attributes']['default_checked'] === true) || $value) {
            if (!isset($config['converter_attributes']['dateRangeField'])) {
                throw new \Exception(
                    'Previous date range configuration parameter should have dateRangeField attribute'
                );
            }
            $currentDateRange = $options[$config['converter_attributes']['dateRangeField']];

            if (isset($currentDateRange['value'])) {
                $currentDateRange = parent::getConvertedValue(
                    $widgetConfig,
                    $currentDateRange,
                    $config,
                    $options
                );
            }

            if ($currentDateRange['type'] !== AbstractDateFilterType::TYPE_LESS_THAN) {
                /**
                 * @var \DateTime $from
                 * @var \DateTime $to
                 */
                $from = $currentDateRange['start'];
                $to   = $currentDateRange['end'];

                $interval        = $from->diff($to);
                $fromDate        = clone $from;
                $result['start'] = $fromDate->sub($interval);
                $result['end']   = clone $from;
                $result['type']  = AbstractDateFilterType::TYPE_BETWEEN;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewValue($value)
    {
        if (!empty($value)) {
            return parent::getViewValue($value);
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormValue(array $config, $value)
    {
        if ($value === null && $config['converter_attributes']['default_checked'] === true) {
            return true;
        }

        return parent::getFormValue($config, $value);
    }
}
