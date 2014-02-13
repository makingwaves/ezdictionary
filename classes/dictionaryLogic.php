<?php
namespace MakingWaves\eZDictionary;

/**
 * Class DictionaryLogic
 *
 * Separated logic for template operator.
 * Class is unit tested, so please update tests on each change.
 *
 * @package MakingWaves\eZDictionary
 */
class DictionaryLogic
{
    /**
     * @var string
     */
    private $operator_value;

    /**
     * @var array
     */
    private static $parent_array = array();

    /**
     * @var array
     */
    private $named_parameters;

    /**
     * @var array
     */
    private $classes = array();

    /**
     * @var CacheMechanism
     */
    private $cache_mechanism;

    /**
     * Default constructor
     * @param string $operator_value
     * @param array $named_parameters
     * @throws DictionaryLogicIncorrectOperatorValueException
     * @throws DictionaryLogicIncorrectOperatorParamException
     * @throws DictionaryLogicMissingParentNodesException
     */
    public function __construct( $operator_value, $named_parameters )
    {
        if ( !is_string( $operator_value ) )
        {
            throw new DictionaryLogicIncorrectOperatorValueException( 'Incorrect operator value' );
        }

        if ( !is_array( $named_parameters ) || sizeof( $named_parameters ) === 0 )
        {
            throw new DictionaryLogicIncorrectOperatorParamException( 'Dictionary operator parameters are missing' );
        }

        $this->operator_value = $operator_value;
        $this->named_parameters = $named_parameters;

        if ( count( self::$parent_array ) === 0 )
        {
            self::$parent_array = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'ParentNodes' );
            if ( !is_array( self::$parent_array ) )
            {
                throw new DictionaryLogicMissingParentNodesException( 'There are no parent nodes defined in INI settings' );
            }
        }

        $this->cache_mechanism = new CacheMechanism( self::$parent_array, $this->getClasses() );
    }

    /**
     * Applies the dictionary markup into operator value
     * @return string
     */
    public function applyDictionary()
    {
        $dictionary_array = $this->getDictionaryArray();

        return $this->generateMarkup( $dictionary_array );
    }

    /**
     * Get dictionary array, handles caching
     * @return array
     */
    public function getDictionaryArray()
    {
        $dictionary_array = $this->cache_mechanism->getCachedData();
        if ( empty( $dictionary_array ) )
        {
            $dictionary_nodes = $this->getWordNodes();
            $dictionary_array = $this->generateDictionaryArray( $dictionary_nodes );
            $this->cache_mechanism->writeToCache( $dictionary_array );
        }

        return $dictionary_array;
    }

    /**
     * Method generates an array of dictionary items basing on given array of nodes
     * @param array $nodes - array of nodes to base dictionary data on
     * @return array
     */
    private function generateDictionaryArray( $nodes )
    {
        if ( sizeof( $nodes ) === 0 )
        {
            \eZDebug::writeError( 'There are no nodes which matches the dictionary settings. Please check the extension configuration (ezdictionary.ini).' );
        }

        $dictionary = array();
        foreach ( $nodes as $node )
        {
            $attrib_values = $this->getAttributeValues( $node );
            if ( !empty( $attrib_values ) )
            {
                $dictionary[$attrib_values['keyword']] = $attrib_values['description'];
            }
        }

        return $dictionary;
    }

    /**
     * Method generates new html markup and returns it
     * @param array $dictionary_array
     * @return string
     */
    private function generateMarkup( $dictionary_array )
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors( true );

        $dom->loadHTML( mb_convert_encoding( $this->operator_value, 'HTML-ENTITIES', "UTF-8" ) );

        $omit_tags = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'OmitTags' );
        $this->processBranchOfDomNodes( $dictionary_array, $dom->childNodes, $omit_tags );

        return utf8_encode( html_entity_decode( $dom->saveHTML() ) );
    }

    /**
     * Method returns the node data for defined attributes.
     * @param \eZContentObjectTreeNode $node
     * @return array
     */
    private function getAttributeValues( \eZContentObjectTreeNode $node )
    {
        $data_map = $node->dataMap();
        $attribs = $this->getClassAttributes( $node->attribute( 'class_identifier' ) );
        $values = array();

        // set keyword value
        $values['keyword'] = $node->attribute( 'name' );
        if ( !empty( $attribs['keyword'] ) )
        {
            if ( !isset( $data_map[$attribs['keyword']] ) )
            {
                \eZDebug::writeError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
                    . $attribs['keyword'] . '" defined in ezdictionary.ini file. Keyword "' . $values['keyword'] . '" won\'t be used.' );

                // in case of missing keyword, it won't be used
                return array();
            }
            else
            {
                $values['keyword'] = $data_map[$attribs['keyword']]->attribute( 'content' );
            }
        }

        // set description value
        if ( !isset( $data_map[$attribs['description']] ) )
        {
            \eZDebug::writeError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
                . $attribs['description'] . '" defined in ezdictionary.ini file. Keyword "' . $values['keyword'] . '" won\'t be used.' );

            // in case of missing description keyword is not used at all
            return array();
        }
        else
        {
            $values['description'] = $data_map[$attribs['description']]->attribute( 'content' );
        }

        return $values;
    }

    /**
     * Method returns an array of class attributes which will be used for dictionary
     * @param string $class_name
     * @return array
     * @throws DictionaryLogicIncorrectAttributeException
     * @throws DictionaryLogicIncorrectClassNameException
     */
    private function getClassAttributes( $class_name )
    {
        if ( !is_string( $class_name ) || strlen( $class_name ) === 0 )
        {
            throw new DictionaryLogicIncorrectClassNameException( 'Incorrect class name' );
        }

        $classes = $this->getClasses();

        if ( !isset( $classes[$class_name][1] ) )
        {
            throw new DictionaryLogicIncorrectAttributeException( 'Class "' . $class_name . '" is not configured properly. Check ezdictionary.ini file.' );
        }

        return array(
            'keyword' => $classes[$class_name][0],
            'description' => $classes[$class_name][1]
        );
    }

    /**
     * Method returns all DictionaryClasses data - containing class names as array keys and attribute names as values
     * @return array
     */
    private function getClasses()
    {
        if( sizeof( $this->classes ) === 0 ) {
            $classes = \eZINI::instance( 'ezdictionary.ini' )->variableArray( 'TemplateOperator', 'DictionaryClasses' );
            $this->classes = $classes === false ? array() : $classes;
        }
        return $this->classes;
    }

    /**
     * Method returns a value of given named parameter
     * @param string $name
     * @return mixed
     * @throws DictionaryLogicIncorrectParameterTypeException
     * @throws DictionaryLogicParameterDoesNotExistException
     */
    private function getParameter( $name )
    {
        if ( !is_string( $name ) )
        {
            throw new DictionaryLogicIncorrectParameterTypeException( 'Parameter name needs to be a string' );
        }

        if ( !isset( $this->named_parameters[$name] ) )
        {
            throw new DictionaryLogicParameterDoesNotExistException( 'Parameter with given name ("' . $name . '") does not exists' );
        }

        return $this->named_parameters[$name];
    }

    /**
     * Method fetches the nodes by names defined in TemplateOperator[Classes] which are children of TemplateOperator[ParentNodes]
     * @return array
     */
    private function getWordNodes()
    {
        $nodes = \eZFunctionHandler::execute( 'content', 'tree', array(
            'class_filter_type' => 'include',
            'class_filter_array' => array_keys( $this->getClasses() ),
            'parent_node_id' => self::$parent_array
        ) );

        return is_array( $nodes ) ? $nodes : array();
    }

    /**
     * Method runs recursive and process all the branches in DOM tree.
     * @param \DOMNodeList $dom_nodes
     * @param array $omit_tags
     * @param array $dictionary_array
     */
    private function processBranchOfDomNodes( $dictionary_array, \DOMNodeList $dom_nodes, $omit_tags )
    {
        // loop all dom nodes for given list
        foreach ( $dom_nodes as $dom_node )
        {
            // omit the html tags defined in ini file
            if ( in_array( $dom_node->nodeName, $omit_tags ) )
            {
                continue;
            }

            // #text = last dom node, contains plain text
            if ( $dom_node->nodeName === '#text' )
            {
                // loop all cached nodes
                foreach( $dictionary_array as $word => $description )
                {
                    $dict_tpl = \eZTemplate::factory();
                    $dict_tpl->setVariable( 'dict_desc', $description );

                    $case_sensitive = $this->getParameter( 'case_sensitive' ) === true ? '' : 'i';
                    $pattern =  '/(\b' . $word . '\b)/' . $case_sensitive;

                    if ( preg_match( $pattern, $dom_node->nodeValue ) )
                    {
                        $dom_node->nodeValue = preg_replace( $pattern, $dict_tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $dom_node->nodeValue );
                    }
                }
            }

            // run the function recursive in case when current node contains children
            if ( $dom_node->childNodes instanceof \DOMNodeList )
            {
                $this->processBranchOfDomNodes( $dictionary_array, $dom_node->childNodes, $omit_tags );
            }
        }
    }
} 