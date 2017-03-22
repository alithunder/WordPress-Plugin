<?php
/*
* Define class aiowaff_Utils
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('aiowaff_Utils') != true) {
    class aiowaff_Utils
    {
        /*
        * Some required plugin information
        */
        const VERSION = '1.0';

        /*
        * Store some helpers config
        */
        public $the_plugin = null;
        public $amzHelper = null;

        static protected $_instance;
        
    
        /*
        * Required __construct() function that initalizes the Ali Framework
        */
        public function __construct( $parent )
        {
            $this->the_plugin = $parent;
            $this->amzHelper = $this->the_plugin->amzHelper;
        }
        
        /**
        * Singleton pattern
        *
        * @return Singleton instance
        */
        static public function getInstance( $parent )
        {
            if (!self::$_instance) {
                self::$_instance = new self($parent);
            }
            
            return self::$_instance;
        }
        

        /**
         * Cache
         */
        //use cache to limits search accesses!
        public function needNewCache($filename, $cache_life) {
        
            // cache file needs refresh!
            if (($statCache = $this->isCacheRefresh($filename, $cache_life))===true || $statCache===0) {
                return true;
            }
            return false;
        }
        
        // verify cache refresh is necessary!
        public function isCacheRefresh($filename, $cache_life) {
            // cache file exists!
            if ($this->verifyFileExists($filename)) {
                $verify_time = time(); // in seconds
                $file_time = filemtime($filename); // in seconds
                $mins_diff = ($verify_time - $file_time) / 60; // in minutes
                if($mins_diff > $cache_life){
                    // new cache is necessary!
                    return true;
                }
                // cache is empty! => new cache is necessary!
                if (filesize($filename)<=0) return 0;
    
                // NO new cache!
                return false;
            }
            // cache file NOT exists! => new cache is necessary!
            return 0;
        }
    
        // write content to local cached file
        public function writeCacheFile($filename, $content, $use_lock=false) {
            $folder = dirname($filename);
            if ( empty($folder) || $folder == '.' || $folder == '/' ) return false;
  
            // cache folder!
            if ( !$this->makedir($folder) ) return false;
            if ( !is_writable($folder) ) return false;

            $has_wrote = false;
            if ( $use_lock ) {

                $fp = @fopen($filename, "wb");
                if ( @flock($fp, LOCK_EX, $wouldblock) ) { // do an exclusive lock
                    $has_wrote = @fwrite($fp, $content);
                    @flock($fp, LOCK_UN, $wouldblock); // release the lock
                }
                @fclose( $fp );
            } else {

                $wp_filesystem = $this->the_plugin->wp_filesystem;
                $has_wrote = $wp_filesystem->put_contents( $filename, $content );
                if ( !$has_wrote ) {
                    $has_wrote = file_put_contents($filename, $content);
                }
            }
            return $has_wrote;
        }
    
        // cache file
        public function getCacheFile($filename) {
            if ($this->verifyFileExists($filename)) {
                
                $wp_filesystem = $this->the_plugin->wp_filesystem;
                $has_wrote = $wp_filesystem->get_contents( $filename );
                if ( !$has_wrote ) {
                    $has_wrote = file_get_contents($filename);
                }
                $content = $has_wrote;
                return $content;
            }
            return false;
        }
        
        // delete cache
        public function deleteCache($filename) {
            if ($this->verifyFileExists($filename)) {
                return unlink($filename);
            }
            return false;
        }
    
        // verify if file exists!
        public function verifyFileExists($file, $type='file') {
            clearstatcache();
            if ($type=='file') {
                if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
                    return false;
                }
                return true;
            } else if ($type=='folder') {
                if (!is_dir($file) || !is_readable($file)) {
                    return false;
                }
                return true;
            }
            // invalid type
            return 0;
        }
    
        // make a folder!
        public function makedir($fullpath) {
            clearstatcache();
            if(file_exists($fullpath) && is_dir($fullpath) && is_readable($fullpath)) {
                return true;
            }else{
                $stat1 = @mkdir($fullpath, 0777, true); // recursive
                $stat2 = @chmod($fullpath, 0777);
                if (!empty($stat1) && !empty($stat2))
                    return true;
            }
            return false;
        }
        
        // get file name/ dot indicate if a .dot will be put in front of image extension, default is not
        public function fileName($fullname)
        {
            $return = substr($fullname, 0, strrpos($fullname, "."));
            return $return;
        }
    
        // get file extension
        public function fileExtension($fullname, $dot=false)
        {
            $return = "";;
            if( $dot == true ) $return .= ".";
            $return .= substr(strrchr($fullname, "."), 1);
            return $return;
        }
    
        public function append_contents( $filename, $contents, $mode = '0777' ) {
            $folder = dirname($filename);
            if ( empty($folder) || $folder == '.' || $folder == '/' ) return false;
  
            // cache folder!
            if ( !$this->makedir($folder) ) return false;
            if ( !is_writable($folder) ) return false;

            if ( !($fp = @fopen($filename, 'ab')) ) {
                return false;
            }
            $stat1 = @fwrite($fp, $contents);
            @fclose($fp);
            $stat2 = @chmod($filename, $mode);
            if (!empty($stat1) && !empty($stat2))
                return true;
            return false;
        }
        
        public function put_contents_gzip( $filename, $contents ) {
            if ( !function_exists('gzcompress') ) return false;
                
            //$gzip = @gzopen($filename, "w9");
            //if ( $gzip ){
            //    gzwrite($gzip, $contents);
            //    gzclose($gzip);
            //}
            
            $gzip = @fopen( $filename, 'w' );
            if ( $gzip ) {
                //$contents = @gzcompress($contents, 9); //zlib (http deflate)
                $contents = @gzencode($contents, 9); //gzip
                //$contents = @gzdeflate($contents, 1); //raw deflate encoding
                @fwrite($gzip, $contents);
                @fclose($gzip);
            }
    
            return true;
        }

        public function get_folder_files_recursive($path) {
        	if ( !$this->verifyFileExists($path, 'folder') ) return 0;

            $size = 0;
            $ignore = array('.', '..', 'cgi-bin', '.DS_Store');
            $files = scandir($path);
  
            foreach ($files as $t) {
                if (in_array($t, $ignore)) continue;
                if (is_dir(rtrim($path, '/') . '/' . $t)) {
                    $size += $this->get_folder_files_recursive(rtrim($path, '/') . '/' . $t);
                } else {
                    $size++;
                }   
            }
            return $size;
        }
        
        public function createFile($filename, $content='') {
            $has_wrote = false;
            if ( $fp = @fopen($filename,'wb') ) {
                $has_wrote = @fwrite($fp, $content);
                @fclose($fp);
            }
            return $has_wrote;
        }



		// Replace last occurance of a String
		public function str_replace_last( $search , $replace , $str ) {
		    if ( ( $pos = strrpos( $str , $search ) ) !== false ) {
		        $search_length  = strlen( $search );
		        $str = substr_replace( $str, $replace, $pos, $search_length );
		    }
		    return $str;
		}
	}
}