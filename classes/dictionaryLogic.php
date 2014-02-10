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
     * @var string
     */
    private static $parent_node_id = 0;

    /**
     * @var array
     */
    private $named_parameters;

    /**
     * @var array
     */
    private $classes = array();

    /**
     * Default constructor
     * @param string $operator_value
     * @param array $named_parameters
     * @throws DictionaryLogicIncorrectOperatorValueException
     * @throws DictionaryLogicIncorrectOperatorParamException
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

        if ( empty( $this->parent_node_id ) )
        {
            $this->parent_node_id = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'ParentNodes' );
        }
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
        $dictionary_array = $this->getCachedData();
        if ( empty( $dictionary_array ) )
        {
            $dictionary_nodes = $this->getWordNodes();
            $dictionary_array = $this->generateDictionaryArray( $dictionary_nodes );
            $this->writeToCache( $dictionary_array );
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
        $new_value = $this->operator_value;

        foreach ( $dictionary_array as $word => $description )
        {
            $dict_tpl = \eZTemplate::factory();
            $dict_tpl->setVariable( 'dict_desc', $description );

            $case_sensitive = $this->getParameter( 'case_sensitive' ) === true ? '' : 'i';
            $pattern =  '/(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $new_value ) )
            {
                $new_value = preg_replace( $pattern, $dict_tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $new_value );
            }
        }

        return $new_value;
    }

    /**
     * Return cached values
     * @return array
     */
    private function getCachedData()
    {
        $path = \eZSys::cacheDirectory() . '/';
        $path .= \eZINI::instance( 'site.ini' )->variable( 'Cache_dictionary', 'path' ) . '/';
        $filename = $path . $this->getCacheHash() . '.cache';

        $cluster_file_handler = \eZClusterFileHandler::instance( $filename );
        $content = $cluster_file_handler->fileFetchContents( $filename );

        return unserialize( $content );
    }

    /**
     * Serialize and cache dictionary data
     * @param array $dictionary_array
     * @return array
     */
    private function writeToCache( $dictionary_array )
    {
        $path = \eZSys::cacheDirectory() . '/';
        $path .= \eZINI::instance( 'site.ini' )->variable( 'Cache_dictionary', 'path' ) . '/';
        $filename = $path . $this->getCacheHash() . '.cache';

        $cluster_file_handler = \eZClusterFileHandler::instance( $filename );
        $cluster_file_handler->fileStoreContents( $filename, serialize( $dictionary_array ) );
    }

    /**
     * Make an identification value, so the cache is automagically updated when objects change.
     * @return string
     */
    private function getCacheHash()
    {
        $parent_node = \eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $this->parent_node_id ) );
        $no_of_subnodes = \eZFunctionHandler::execute( 'content', 'tree_count',
            array( 'parent_node_id' => $this->parent_node_id,
                   'class_filter_type' => 'include',
                   'class_filter_array' => array_keys( $this->getClasses() ),
         ) );

        $id = $parent_node->attribute( 'modified_subnode' ) . '_' . $no_of_subnodes;
        return md5( $id );
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
        $attributes = array();

        if ( !isset( $classes[$class_name][1] ) )
        {
            throw new DictionaryLogicIncorrectAttributeException( 'Class "' . $class_name . '" is not configured properly. Check ezdictionary.ini file.' );
        }

        $attributes = array(
            'keyword' => $classes[$class_name][0],
            'description' => $classes[$class_name][1]
        );

        return $attributes;
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
            throw new DictionaryLogicParameterDoesNotExistException( 'Parameter with given name does not exists' );
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
            'parent_node_id' => $this->parent_node_id
        ) );

        return is_array( $nodes ) ? $nodes : array();
    }
} 