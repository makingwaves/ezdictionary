<?php
namespace MakingWaves\eZDictionary\Tests;

use \MakingWaves\eZDictionary\DictionaryLogic;

/**
 * Class contains tests for DictionaryLogic class. Can be run by:
 * $ php tests/runtests.php --dsn mysql://root:pass@localhost/db_name --filter="DictionaryLogicTest" --db-per-test
 */
class DictionaryLogicTest extends \ezpDatabaseTestCase
{
    /**
     * Method sets the ini settings for dictionary operator
     * @param bool $load_original
     * @param array $custom_settings
     */
    private function setIniSettings( $load_original = true, $custom_settings = array() )
    {
        if ( $load_original === true )
        {
            $original_ini = \eZIni::instance( 'ezdictionary.ini', 'extension/ezdictionary/settings' );
            foreach ( $original_ini->groups() as $group_name => $group )
            {
                foreach ( $group as $var_name => $var_data )
                {
                    \ezpINIHelper::setINISetting( 'ezdictionary.ini', $group_name, $var_name, $var_data );
                }
            }
        }

        foreach ( $custom_settings as $group_name => $group )
        {
            foreach ( $group as $var_name => $var_data )
            {
                \ezpINIHelper::setINISetting( 'ezdictionary.ini', $group_name, $var_name, $var_data );
            }
        }
    }

    /**
     * Returns an array of incorrect operator values.
     * @return array
     */
    public function providerIncorrectOperatorValue()
    {
        return array(
            array( false ), array( null ), array( true ), array( array() ), array( -1 ), array( 1 )
        );
    }

    /**
     * @dataProvider providerIncorrectOperatorValue
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicIncorrectOperatorValueException
     */
    public function testIncorrectOperatorValue( $operator_value )
    {
        new DictionaryLogic( $operator_value, array() );
    }

    /**
     * Returns an array of incorrect operator params
     * @return array
     */
    public function providerIncorrectOperatorParam()
    {
        return array(
            array( false ), array( null ), array( -1 ), array( 1 ), array( 'test' ), array( array() )
        );
    }

    /**
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicIncorrectOperatorParamException
     * @dataProvider providerIncorrectOperatorParam
     */
    public function testIncorrectOperatorParam( $params )
    {
        new DictionaryLogic( '', $params );
    }

    /**
     * Returns an array of correct data set for OperatorLogic constructor
     * @return array
     */
    public function providerCorrectOperatorConstructor()
    {
        return array(
            array( 'test value', array( 'test' ) ), array( '', array( 1, 2 ) )
        );
    }

    /**
     * Testing correct behavior of OperatorLogic constructor
     * @dataProvider providerCorrectOperatorConstructor
     */
    public function testCorrectOperatorConstructor( $operator_value, $named_parameters )
    {
        $dictionary_logic = new DictionaryLogic( $operator_value, $named_parameters );

        $value_property = new \ReflectionProperty( 'MakingWaves\eZDictionary\DictionaryLogic', 'operator_value' );
        $value_property->setAccessible( true );
        $this->assertTrue( is_string( $value_property->getValue( $dictionary_logic ) ) );

        $param_property = new \ReflectionProperty( 'MakingWaves\eZDictionary\DictionaryLogic', 'named_parameters' );
        $param_property->setAccessible( true );
        $value = $param_property->getValue( $dictionary_logic );
        $this->assertTrue( is_array( $value ) && sizeof( $value ) > 0 );
    }

    /**
     * Test the method which returns a classes list which are defined in ini file
     */
    public function testGetClasses()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getClasses' );
        $method->setAccessible( true );

        $result = $method->invoke( $dictionary_logic );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) === 0, 'Result needs to be an empty array at this point' );

        // load ini settings
        $this->setIniSettings();
        $result = $method->invoke( $dictionary_logic );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) > 0, 'Result needs to be a non-empty array at this point' );
    }

    /**
     * Test for method getWordNodes
     */
    public function testGetWordNodes()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getWordNodes' );
        $method->setAccessible( true );

        $result = $method->invoke( $dictionary_logic );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) === 0, 'Result needs to be an empty array at this point' );

        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name;short_name'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        $result = $method->invoke( $dictionary_logic );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) > 0, 'Result needs to be a non-empty array at this point' );
    }

    /**
     * Test correct behaviour of getClassAttributes method
     */
    public function testGetClassAttributesCorrect()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getClassAttributes' );
        $method->setAccessible( true );

        $this->setIniSettings();
        $result = $method->invoke( $dictionary_logic, 'event' );
        $this->assertTrue( is_array( $result ) && isset( $result['keyword'], $result['description'] ) );
    }

    /**
     * Data provider for testGetClassAttributesIncorrectClass()
     * @return array
     */
    public function providerGetClassAttributesIncorrectClass()
    {
        return array(
            array( false ), array( null ), array( true ), array( array() ), array( -1 ), array( 1 ), array( '' )
        );
    }

    /**
     * @dataProvider providerGetClassAttributesIncorrectClass
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicIncorrectClassNameException
     */
    public function testGetClassAttributesIncorrectClass( $class_name )
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getClassAttributes' );
        $method->setAccessible( true );

        $method->invoke( $dictionary_logic, $class_name );
    }

    /**
     * Testing the case when class name is correct, but it's not defined in INI file
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicIncorrectAttributeException
     */
    public function testGetClassAttributesIncorrectAttribute()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getClassAttributes' );
        $method->setAccessible( true );

        $method->invoke( $dictionary_logic, 'test' );
    }

    /**
     * Testing correct behaviour of getAttributeValues() method
     */
    public function testGetAttributeValuesCorrect()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getAttributeValues' );
        $method->setAccessible( true );

        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name;short_name'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        $node = \eZFunctionHandler::execute( 'content', 'node', array(
            'node_id' => 2
        ) );

        $result = $method->invoke( $dictionary_logic, $node );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) === 2 );
    }

    /**
     * @dataProvider providerGetClassAttributesIncorrectClass
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testGetAttributeValuesIncorrectInput( $input )
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getAttributeValues' );
        $method->setAccessible( true );

        $method->invoke( $dictionary_logic, $input );
    }

    /**
     * Test for situation when defined in INI file attributes doesn't exist in node
     */
    public function testGetAttributeValuesAttributesNotExists()
    {
        $dictionary_logic = new DictionaryLogic( 'test', array( 'test' ) );
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getAttributeValues' );
        $method->setAccessible( true );

        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name_test;short_name_test'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        $node = \eZFunctionHandler::execute( 'content', 'node', array(
            'node_id' => 2
        ) );

        $result = $method->invoke( $dictionary_logic, $node );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) === 0 );
    }

    /**
     * Test correct behaviour of getDictionaryArray() method
     */
    public function testGetDictionaryArrayCorrect()
    {
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getDictionaryArray' );
        $method->setAccessible( true );

        // test empty input
        $result = $method->invoke( new DictionaryLogic( 'test', array( 'test' ) ), array( ) );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) === 0 );

        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name;short_name'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        // test not empty correct input
        $result = $method->invoke( new DictionaryLogic( 'test', array( 'test' ) ) );
        $this->assertTrue( is_array( $result ) && sizeof( $result ) > 0 );
    }

    /**
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicIncorrectParameterTypeException
     * @dataProvider providerIncorrectOperatorValue
     */
    public function testGetParameterIncorrectInputType( $input )
    {
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getParameter' );
        $method->setAccessible( true );

        $method->invoke( new DictionaryLogic( 'test', array( 'test' ) ), $input );
    }

    /**
     * @expectedException \MakingWaves\eZDictionary\DictionaryLogicParameterDoesNotExistException
     */
    public function testGetParameterNotExists()
    {
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getParameter' );
        $method->setAccessible( true );

        $method->invoke( new DictionaryLogic( 'test', array( 'test' ) ), 'param_not_exists' );
    }

    /**
     * Tests correct behaviour of getParameter() method
     */
    public function testGetParameterCorrect()
    {
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'getParameter' );
        $method->setAccessible( true );

        $test_value = 'value';
        $result = $method->invoke( new DictionaryLogic( 'test', array( 'test' => $test_value ) ), 'test' );
        $this->assertEquals( $test_value, $result );
    }

    /**
     * Tests correct behaviour of generateMarkup() method
     */
    public function testGenerateMarkup()
    {
        $method = new \ReflectionMethod( 'MakingWaves\eZDictionary\DictionaryLogic', 'generateMarkup' );
        $method->setAccessible( true );

        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name;short_name'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        $result = $method->invoke( new DictionaryLogic( 'test', array( 'test' => 'test' ) ) );
        $this->assertTrue( is_string( $result ) );
    }

    /**
     * Testing applyDictionary() method against working cache mechanism
     */
    public function testApplyDictionaryCacheWorks()
    {
        $dictionary = new DictionaryLogic( 'test', array( 'case_sensitive' => 'true' ) );
        // load ini settings
        $this->setIniSettings( true, array(
            'TemplateOperator' => array(
                'DictionaryClasses' => array(
                    'folder' => 'name;short_name'
                ),
                'ParentNodes' => array( 1 )
            )
        ) );

        // cache is stored
        $result = $dictionary->applyDictionary();
        $this->assertTrue( is_string( $result ) );

        // now change the private variable value
        $property = new \ReflectionProperty( 'MakingWaves\eZDictionary\DictionaryLogic', 'cache' );
        $property->setAccessible( true );
        $property->setValue( array( 'new_key' => 'new_value' ) );

        // run the method again, a result should be same
        $result2 = $dictionary->applyDictionary();
        $this->assertTrue( is_string( $result2 ) );
        $this->assertEquals( $result, $result2 );
    }

    /**
     * Method is fired when exiting the test
     */
    public function tearDown()
    {
        \ezpINIHelper::restoreINISettings();
        parent::tearDown();
    }
} 