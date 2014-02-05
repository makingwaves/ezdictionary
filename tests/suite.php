<?php
namespace MakingWaves\ezDictionary\Tests;

/**
 * Unit Testing configuration class
 */
class DictionaryTestSuite extends \ezpDatabaseTestSuite
{
    public function __construct()
    {
        parent::__construct();
        $this->insertDefaultData = false;
        $this->setName( 'eZDictionary extension test suite' );

        // Adding tests
        $this->addTestSuite( 'MakingWaves\eZDictionary\Tests\DictionaryLogicTest' );
    }

    public static function suite()
    {
        return new self();
    }
}