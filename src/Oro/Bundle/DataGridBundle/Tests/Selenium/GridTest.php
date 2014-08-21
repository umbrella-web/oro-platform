<?php

namespace Oro\Bundle\DataGridBundle\Tests\Selenium;

use Oro\Bundle\UserBundle\Tests\Selenium\Pages\Users;
use Oro\Bundle\TestFrameworkBundle\Test\Selenium2TestCase;

/**
 * Class GridTest
 *
 * @package Oro\Bundle\DataGridBundle\Tests\Selenium
 */
class GridTest extends Selenium2TestCase
{
    public function testSelectPage()
    {
        $this->markTestIncomplete('Exception is occurred during test');
        $this->login();
        $users = new Users($this);
        $userData = $users->getRandomEntity();
        $this->assertTrue($users->entityExists($userData));
        $users->changePage(2);
        $this->assertFalse($users->entityExists($userData));
        $users->changePage(1);
        $this->assertTrue($users->entityExists($userData));
    }

    public function testNextPage()
    {
        $this->login();
        $users = new Users($this);
        //check count of users, continue only for BAP
        if ($users->getPagesCount() == 1) {
            $this->markTestSkipped("Test skipped for current environment");
        }
        $userData = $users->getRandomEntity();
        $this->assertTrue($users->entityExists($userData));
        $users->nextPage();
        $this->assertFalse($users->entityExists($userData));
        $users->previousPage();
        $this->assertTrue($users->entityExists($userData));
    }

    public function testPrevPage()
    {
        $this->login();
        $users = new Users($this);
        //check count of users, continue only for BAP
        if ($users->getPagesCount() == 1) {
            $this->markTestSkipped("Test skipped for current environment");
        }
        $userData = $users->getRandomEntity();
        $this->assertTrue($users->entityExists($userData));
        $users->nextPage();
        $this->assertFalse($users->entityExists($userData));
        $users->previousPage();
        $this->assertTrue($users->entityExists($userData));
    }

    /**
     * @dataProvider filterData
     */
    public function testFilterBy($filterName, $condition)
    {
        $this->login();
        $users = new Users($this);
        $userData = $users->getRandomEntity();
        $this->assertTrue(
            $users->filterBy($filterName, $userData[strtoupper($filterName)], $condition)
                ->entityExists($userData)
        );
        $this->assertEquals(1, $users->getRowsCount());
        $users->clearFilter($filterName);
    }

    /**
     * Data provider for filter tests
     *
     * @return array
     */
    public function filterData()
    {
        return array(
            //'ID' => array('ID', '='),
            'Username' => array('Username', 'is equal to'),
            'Email' => array('Primary Email', 'contains'),
            //'First name' => array('First name', 'is equal to'),
            //'Birthday' => array('Birthday', '')
        );
    }

    public function testAddFilter()
    {
        $this->login();
        $users = new Users($this);
        $userData = $users->getRandomEntity();
        $this->assertTrue($users->entityExists($userData));
        $countOfRecords = $users->getRowsCount();
        $this->assertEquals(
            $countOfRecords,
            $users->getRowsCount()
        );

        $this->assertEquals(
            1,
            $users->addFilter('Primary Email')
                ->filterBy('Primary Email', $userData[strtoupper('Primary Email')], 'is equal to')
                ->getRowsCount()
        );
    }

    /**
     * Tests that order in columns works correct
     *
     * @param string $columnName
     * @dataProvider columnTitle
     */
    public function testSorting($columnName)
    {
        $this->login();
        $users = new Users($this);
        //check count of users, continue only for BAP
        if ($users->getPagesCount() == 1) {
            $this->markTestSkipped("Test skipped for current environment");
        }
        $users->changePageSize('last');
        $columnId = $users->getColumnNumber($columnName);

        //test descending order
        $columnOrder = $users->sortBy($columnName, 'desc')->getColumn($columnId);

        if ($columnName == 'Birthday') {
            $dateArray = array();
            foreach ($columnOrder as $value) {
                $date = strtotime($value);
                $dateArray[] = $date;
            }
            $columnOrder = $dateArray;
        }
        $sortedColumnOrder = $columnOrder;
        sort($sortedColumnOrder);
        $sortedColumnOrder = array_reverse($sortedColumnOrder);

        $this->assertEquals(
            $sortedColumnOrder,
            $columnOrder,
            print_r(array('expected' => $sortedColumnOrder, 'actual' => $columnOrder), true)
        );
        //change page size to 10 and refresh grid
        $users->changePageSize('first');
        $users->sortBy($columnName, 'asc');
        $columnOrder = $users->sortBy($columnName, 'desc')->getColumn($columnId);
        $this->assertTrue(
            $columnOrder === array_slice($sortedColumnOrder, 0, 10),
            print_r(array('expected' => $sortedColumnOrder, 'actual' => $columnOrder), true)
        );

        //test ascending order
        $users->changePageSize('last');
        $columnOrder = $users->sortBy($columnName, 'asc')->getColumn($columnId);

        if ($columnName == 'Birthday') {
            $dateArray = array();
            foreach ($columnOrder as $value) {
                $date = strtotime($value);
                $dateArray[] = $date;
            }
            $columnOrder = $dateArray;
        }
        $sortedColumnOrder = $columnOrder;
        natcasesort($sortedColumnOrder);

        $this->assertTrue(
            $columnOrder === $sortedColumnOrder,
            print_r(array('expected' => $sortedColumnOrder, 'actual' => $columnOrder), true)
        );
        //change page size to 10 and refresh grid
        $users->changePageSize('first');
        $users->sortBy($columnName, 'desc');
        $columnOrder = $users->sortBy($columnName, 'asc')->getColumn($columnId);
        $this->assertTrue(
            $columnOrder === array_slice($sortedColumnOrder, 0, 10),
            print_r(array('expected' => $sortedColumnOrder, 'actual' => $columnOrder), true)
        );
    }

    /**
     * Data provider for test sorting
     *
     * @return array
     */
    public function columnTitle()
    {
        return array(
            //'ID' => array('ID'),
            'Username' => array('Username'),
            //'Email' => array('Email'),
            //'First name' => array('First name'),
            //'Birthday' => array('Birthday'),
            //'Company' => array('Company'),
            //'Salary' => array('Salary'),
        );
    }
}
