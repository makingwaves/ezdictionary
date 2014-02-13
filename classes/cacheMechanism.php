<?php
namespace MakingWaves\eZDictionary;

/**
 * Class CacheMechanism
 * @package MakingWaves\eZDictionary
 */
class CacheMechanism
{
    /**
     * @var array
     */
    private $parent_array = array();

    /**
     * @var array
     */
    private $classes = array();

    /**
     * @param array $parent_array
     * @param array $classes
     * @throws CacheMechanismIncorrectParentArray
     */
    public function __construct( array $parent_array, array $classes )
    {
        if ( count( $parent_array ) === 0 )
        {
            throw new CacheMechanismIncorrectParentArray( 'Array of parent nodes cannot be empty' );
        }

        $this->parent_array = $parent_array;
        $this->classes = $classes;
    }

    /**
     * Return cached values
     * @return array
     */
    public function getCachedData()
    {
        $filename = $this->getCacheFilename();
        $cluster_file_handler = \eZClusterFileHandler::instance( $filename );
        $content = $cluster_file_handler->fileFetchContents( $filename );

        return unserialize( $content );
    }

    /**
     * Serialize and cache dictionary data
     * @param array $dictionary_array
     * @return array
     */
    public function writeToCache( $dictionary_array )
    {
        $filename = $this->getCacheFilename();
        $cluster_file_handler = \eZClusterFileHandler::instance( $filename );
        $cluster_file_handler->fileStoreContents( $filename, serialize( $dictionary_array ) );
    }

    /**
     * Build the cache file path with hash name
     * Makes an identification value, so the cache is automagically updated when objects change.
     * @return string
     */
    private function getCacheFilename()
    {
        $timestamps = array();
        foreach( $this->parent_array as $node_id )
        {
            $node = \eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $node_id ) );
            $timestamps[] = $node->attribute( 'modified_subnode' );
        }

        $no_of_subnodes = \eZFunctionHandler::execute( 'content', 'tree_count',
            array( 'parent_node_id' => $this->parent_array,
                'class_filter_type' => 'include',
                'class_filter_array' => array_keys( $this->classes ),
            ) );
        $id = md5( join( '_', $timestamps ) . '_' . $no_of_subnodes );

        $path = \eZSys::cacheDirectory() . '/';
        $path .= \eZINI::instance( 'site.ini' )->variable( 'Cache_dictionary', 'path' );

        return "$path/$id.cache";
    }
}