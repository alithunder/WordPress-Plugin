<?php
/**
 *	Author: Ali
 *	Name: 	
 *	
**/
! defined( 'ABSPATH' ) and exit;

if(class_exists('aiowaffGenericHelper') != true) {
	class aiowaffGenericHelper extends aiowaff
	{
		private $the_plugin = null;
		public $amz_settings = array();
		
		static protected $_instance;
        
        const MSG_SEP = '—'; // messages html bullet // '&#8212;'; // messages html separator
        
		
        /**
         * The constructor
         */
		public function __construct( $the_plugin=array() ) 
		{
			$this->the_plugin = $the_plugin; 
			
			// get all amazon settings options
            if ( !empty($this->the_plugin) && !empty($this->the_plugin->amz_settings) ) {
                $this->amz_settings = $this->the_plugin->amz_settings;
            } else {
                $this->amz_settings = @unserialize( get_option( $this->the_plugin->alias . '_amazon' ) );
            }
		}
		
		/**
	    	* Singleton pattern
	    	*
	    	* @return pspGoogleAuthorship Singleton instance
	    	*/
		static public function getInstance( $the_plugin=array() )
		{
			if (!self::$_instance) {
				self::$_instance = new self( $the_plugin );
			}

			return self::$_instance;
		}


		/**
		 * Assets download methods
		 */
		public function get_asset_by_id( $asset_id, $inprogress=false, $include_err=false, $include_invalid_post=false ) {
			require( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/modules/assets_download/init.php' );
			$aiowaffAssetDownloadCron = new aiowaffAssetDownload();
			
			return $aiowaffAssetDownloadCron->get_asset_by_id( $asset_id, $inprogress, $include_err, $include_invalid_post );
		}
		
		public function get_asset_by_postid( $nb_dw, $post_id, $include_variations, $inprogress=false, $include_err=false, $include_invalid_post=false ) {
			require( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/modules/assets_download/init.php' );
			$aiowaffAssetDownloadCron = new aiowaffAssetDownload();
			
			$ret = $aiowaffAssetDownloadCron->get_asset_by_postid( $nb_dw, $post_id, $include_variations, $inprogress, $include_err, $include_invalid_post );
            return $ret;
		}

		public function get_asset_multiple( $nb_dw='all', $inprogress=false, $include_err=false, $include_invalid_post=false ) {
			require( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/modules/assets_download/init.php' );
			$aiowaffAssetDownloadCron = new aiowaffAssetDownload();
			
			return $aiowaffAssetDownloadCron->get_asset_multiple( $nb_dw, $inprogress, $include_err, $include_invalid_post );
		}
		
		
		/**
		 * Category Slug clean duplicate & Other Bug Fixes
		 */
		public function category_slug_clean_all( $retType = 'die' ) {
			global $wpdb;
			
			$q = "SELECT 
 a.term_id, a.name, a.slug, b.parent, b.count
 FROM {$wpdb->terms} AS a
 LEFT JOIN {$wpdb->term_taxonomy} AS b ON a.term_id = b.term_id
 WHERE 1=1 AND b.taxonomy = 'product_cat'
;";
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) {
				$ret['status'] = 'valid';
				$ret['msg_html'] = __('could not retrieve category slugs!', $this->the_plugin->localizationName);
				if ( $retType == 'die' ) die(json_encode($ret));
				else return $ret;
			}
			
			$upd = 0;
			foreach ($res as $key => $value) {
				$term_id = $value->term_id;
				$name = $value->name;
				$slug = $value->slug;

				$__arr = explode( "-" , $slug );
				$__arr = array_unique( $__arr );
				$slug = implode( "-" , $__arr );

				// execution/ update
				$q_upd = "UPDATE {$wpdb->terms} AS a SET a.slug = '%s' 
 WHERE 1=1 AND a.term_id = %s;";
 				$q_upd = sprintf( $q_upd, $slug, $term_id );
				$res_upd = $wpdb->query( $q_upd );

				if ( !empty($res_upd) ) $upd++;
			}
			
			$ret['status'] = 'valid';
			$ret['msg_html'] = $upd . __(' category slugs updated!', $this->the_plugin->localizationName);
			if ( $retType == 'die' ) die(json_encode($ret));
			else return $ret;
		}
		
		public function clean_orphaned_amz_meta_all( $retType = 'die' ) {
			global $wpdb;
			
			$ret = array();
			$key = '_amzASIN';
			$_key = $key;
			if ( $_key == '_amzASIN' ) $key = '_aiowaff_prodid';
			
			//$get_amzASINS = $wpdb->get_results("SELECT a.meta_id, a.post_id FROM ". $wpdb->postmeta ." AS a LEFT OUTER JOIN ". $wpdb->posts ." AS b ON a.post_id=b.ID WHERE a.meta_key='_amzASIN' AND b.ID IS NULL");
			$get_amzASINS = $wpdb->get_results("SELECT a.meta_id, a.post_id FROM ". $wpdb->postmeta ." AS a LEFT OUTER JOIN ". $wpdb->posts ." AS b ON a.post_id=b.ID WHERE a.meta_key='$key' AND (b.ID IS NULL OR b.post_type NOT IN ('product', 'product_variation'))");
			// @2015, october 29 future update/bug fix: a.meta_key='_amzASIN' should be replaced with something like a.meta_key regexp '^(_amzASIN|_amzaff_)'
			
			$deleteMetaASINS = array();
			foreach ($get_amzASINS as $meta_id) {
				$deleteMetaASINS[] = $meta_id->meta_id;
			}
			if( count($deleteMetaASINS) > 0 ) {
				$deleteInvalidAmzMeta = $wpdb->query("DELETE FROM ".$wpdb->postmeta." WHERE meta_id IN (".(implode(',', $deleteMetaASINS)).")");
			}
			
			if( count($deleteMetaASINS) > 0 && $deleteInvalidAmzMeta > 0 ) {
				$ret['status'] = 'valid';
				$ret['msg_html'] = $deleteInvalidAmzMeta . ' orphaned amz meta cleared.';
			}elseif( count($deleteMetaASINS) == 0 ) {
				$ret['status'] = 'valid';
				$ret['msg_html'] = 'No orphaned amz meta to clean.';
			}else{
				$ret['status'] = 'invalid';
				$ret['msg_html'] = 'Error clearing orphaned amz meta.';
			}
			  
			if ( $retType == 'die' ) die(json_encode($ret));
			else return $ret;
		}

        public function clean_orphaned_prod_assets_all( $retType = 'die' ) {
            global $wpdb;
            
            $ret = array(
                'status'        => 'invalid',
                'msg_html'      => 'found and deleted: %s orphaned products, %s assets associated to orphaned products.'
            );
            
            $tables = array('assets' => $wpdb->prefix . 'amz_assets', 'products' => $wpdb->prefix . 'amz_products', 'posts' => $wpdb->prefix . 'posts');
            
            //SELECT COUNT(a.post_id) FROM wp_amz_products AS a LEFT JOIN wp_posts AS b ON a.post_id = b.ID WHERE 1=1 AND ISNULL(b.ID);
            $nb_products = (int) $wpdb->get_var("SELECT COUNT(a.post_id) as nb FROM ". $tables['products'] ." AS a LEFT JOIN ". $wpdb->posts ." AS b ON a.post_id = b.ID WHERE 1=1 AND ISNULL(b.ID);");
            
            //SELECT COUNT(a.post_id) FROM wp_amz_assets AS a LEFT JOIN wp_amz_products AS b ON a.post_id = b.post_id WHERE 1=1 AND ISNULL(b.post_id);
            $nb_assets = (int) $wpdb->get_var("SELECT COUNT(a.post_id) as nb FROM ". $tables['assets'] ." AS a LEFT JOIN ". $tables['products'] ." AS b ON a.post_id = b.post_id WHERE 1=1 AND ISNULL(b.post_id);");
            
            $ret['status'] = 'valid';
            $ret['msg_html'] = sprintf( $ret['msg_html'], (int) $nb_products, (int) $nb_assets);
 
            if ( $nb_products > 0 ) {
                //delete a FROM wp_amz_products AS a LEFT JOIN wp_posts AS b ON a.post_id = b.ID WHERE 1=1 AND ISNULL(b.ID);
                $delete_products = $wpdb->query("delete a FROM " . $tables['products'] . " as a LEFT JOIN " . $wpdb->posts . " AS b ON a.post_id = b.ID WHERE 1=1 AND ISNULL(b.ID);");
            }
            if ( $nb_assets > 0 ) {
                //delete a FROM wp_amz_assets AS a LEFT JOIN wp_amz_products AS b ON a.post_id = b.post_id WHERE 1=1 AND ISNULL(b.post_id);
                $delete_assets = $wpdb->query("delete a FROM " . $tables['assets'] . " as a LEFT JOIN " . $tables['products'] . " AS b ON a.post_id = b.post_id WHERE 1=1 AND ISNULL(b.post_id);");
            }
            //var_dump('<pre>', $delete_products, $delete_assets, '</pre>'); die('debug...'); 
            
            if ( $retType == 'die' ) die(json_encode($ret));
            else return $ret;
        }

		public function fix_product_attributes_all( $retType = 'die' ) {
			global $wpdb;
			
			$ret = array(
				'status'		=> 'valid',
				'msg_html'		=> array(), 
			);
			$key = '_amzASIN';
			$_key = $key;
			if ( $_key == '_amzASIN' ) $key = '_aiowaff_prodid';
			
			$themetas = array('_product_attributes', '_product_version');
			foreach ($themetas as $themeta) { // foreach metas

				$q = "select * from $wpdb->postmeta as pm where 1=1 and meta_key regexp '$themeta' and post_id in ( select p.ID from $wpdb->posts as p left join $wpdb->postmeta as pm2 on p.ID = pm2.post_id where 1=1 and pm2.meta_key='$key' and !isnull(p.ID) and p.post_type in ('product') );";
				$res = $wpdb->get_results( $q );
				if ( !$res || !is_array($res) ) {
					//$ret['status'] = 'valid';
					if ( !is_array($res) ) {
						$ret['msg_html'][] = sprintf( __('%s fix: no products needed attributes fixing!', $this->the_plugin->localizationName), $themeta );
					} else {
						$ret['msg_html'][] = sprintf( __('%s fix: cannot retrieve products for attributes fixing!', $this->the_plugin->localizationName), $themeta );
					}
					//if ( $retType == 'die' ) die(json_encode($ret));
					//else return $ret;
				}
				else {
					$upd = 0;
					foreach ($res as $key => $value) {
						if ( '_product_attributes' == $themeta ) {
							$__ = maybe_unserialize($value->meta_value);
							$__ = maybe_unserialize($__);
							
							// execution/ update
							//$__ = serialize($__);
							//$q_upd = "UPDATE $wpdb->postmeta AS pm SET pm.meta_value = '%s' WHERE 1=1 AND pm.meta_id = %s;";
			 				//$q_upd = sprintf( $q_upd, $__, $value->meta_id );
							//$res_upd = $wpdb->query( $q_upd );
							
							$__orig = $__;
							if ( !empty($__) && is_array($__) ) {
								foreach ($__ as $k => $v) {
									if ( isset($v['is_visible'], $v['is_variation'], $v['is_taxonomy']) ) {
										if ( ($v['is_visible'] == '1') && ($v['is_variation'] == '1') && ($v['is_taxonomy'] == '1') ) {
											$__["$k"]['value'] = '';
										}
									}
								}
							}
			  
							$res_upd = update_post_meta($value->post_id, $themeta, $__);
			  				add_post_meta($value->post_id, '_amzaff_orig'.$themeta, $__orig, true);
							if ( !empty($res_upd) ) $upd++;
						}
						else {
							$__ = $this->the_plugin->force_woocommerce_product_version($value->meta_value, '2.4.0', '9.9.9');
							
							$res_upd = update_post_meta($value->post_id, $themeta, $__);
							if ( !empty($res_upd) ) $upd++;
						}
					}
					
					//$ret['status'] = 'valid';
					$ret['msg_html'][] = sprintf( __('%s fix: %s products needed attributes fixing!', $this->the_plugin->localizationName), $themeta, $upd );
				}
			} // end foreach themetas

			$ret['msg_html'] = implode('<br />', $ret['msg_html']);
			if ( $retType == 'die' ) die(json_encode($ret));
			else return $ret;
		}


		/**
		 * Attributes clean duplicate
		 */
		public function attrclean_getDuplicateList() {
			global $wpdb;

			// $q = "SELECT COUNT(a.term_id) AS nb, a.name, a.slug FROM {$wpdb->terms} AS a WHERE 1=1 GROUP BY a.name HAVING nb > 1;";
			$q = "SELECT COUNT(a.term_id) AS nb, a.name, a.slug, b.term_taxonomy_id, b.taxonomy, b.count FROM {$wpdb->terms} AS a
 LEFT JOIN {$wpdb->term_taxonomy} AS b ON a.term_id = b.term_id
 WHERE 1=1 AND b.taxonomy REGEXP '^pa_' GROUP BY a.name, b.taxonomy HAVING nb > 1
 ORDER BY a.name ASC
;";
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) return false;
			
			$ret = array();
			foreach ($res as $key => $value) {
				$name = $value->name;
				$taxonomy = $value->taxonomy;
				$ret["$name@@$taxonomy"] = $value;
			}
			return $ret;
		}
		
		public function attrclean_getTermPerDuplicate( $term_name, $taxonomy ) {
			global $wpdb;
			
			$q = "SELECT a.term_id, a.name, a.slug, b.term_taxonomy_id, b.taxonomy, b.count FROM {$wpdb->terms} AS a
 LEFT JOIN {$wpdb->term_taxonomy} AS b ON a.term_id = b.term_id
 WHERE 1=1 AND a.name=%s AND b.taxonomy=%s ORDER BY a.slug ASC;";
 			$q = $wpdb->prepare( $q, $term_name, $taxonomy );
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) return false;
			
			$ret = array();
			foreach ($res as $key => $value) {
				$ret[$value->term_taxonomy_id] = $value;
			}
			return $ret;
		}
		
		public function attrclean_removeDuplicate( $first_term, $terms=array(), $debug = false ) {
			if ( empty($terms) || !is_array($terms) ) return false;

			$term_id = array();
			$term_taxonomy_id = array();
			foreach ($terms as $k => $v) {
				$term_id[] = $v->term_id;
				$term_taxonomy_id[] = $v->term_taxonomy_id;
				$taxonomy = $v->taxonomy;
			}
			// var_dump('<pre>',$first_term, $term_id, $term_taxonomy_id, $taxonomy,'</pre>');  

			$ret = array();
			$ret['term_relationships'] = $this->attrclean_remove_term_relationships( $first_term, $term_taxonomy_id, $debug );
			$ret['terms'] = $this->attrclean_remove_terms( $term_id, $debug );
			$ret['term_taxonomy'] = $this->attrclean_remove_term_taxonomy( $term_taxonomy_id, $taxonomy, $debug );
			// var_dump('<pre>',$ret,'</pre>');  
			return $ret;
		}
		
		private function attrclean_remove_term_relationships( $first_term, $term_taxonomy_id, $debug = false ) {
			global $wpdb;
			
			$idList = (is_array($term_taxonomy_id) && count($term_taxonomy_id)>0 ? implode(', ', array_map(array($this->the_plugin, 'prepareForInList'), $term_taxonomy_id)) : 0);

			if ( $debug ) {
			$q = "SELECT a.object_id, a.term_taxonomy_id FROM {$wpdb->term_relationships} AS a
 WHERE 1=1 AND a.term_taxonomy_id IN (%s) ORDER BY a.object_id ASC, a.term_taxonomy_id;";
 			$q = sprintf( $q, $idList );
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) return false;
			
			$ret = array();
			$ret[] = 'object_id, term_taxonomy_id';
			foreach ($res as $key => $value) {
				$term_taxonomy_id = $value->term_taxonomy_id;
				$ret["$term_taxonomy_id"] = $value;
			}
			return $ret;
			}
			
			// execution/ update
			$q = "UPDATE {$wpdb->term_relationships} AS a SET a.term_taxonomy_id = '%s' 
 WHERE 1=1 AND a.term_taxonomy_id IN (%s);";
 			$q = sprintf( $q, $first_term, $idList );
			$res = $wpdb->query( $q );
			$ret = $res;
			return $ret;
		}
		
		private function attrclean_remove_terms( $term_id, $debug = false ) {
			global $wpdb;
			
			$idList = (is_array($term_id) && count($term_id)>0 ? implode(', ', array_map(array($this->the_plugin, 'prepareForInList'), $term_id)) : 0);

			if ( $debug ) {
			$q = "SELECT a.term_id, a.name FROM {$wpdb->terms} AS a
 WHERE 1=1 AND a.term_id IN (%s) ORDER BY a.name ASC;";
 			$q = sprintf( $q, $idList );
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) return false;
			
			$ret = array();
			$ret[] = 'term_id, name';
			foreach ($res as $key => $value) {
				$term_id = $value->term_id;
				$ret["$term_id"] = $value;
			}
			return $ret;
			}
			
			// execution/ update
			$q = "DELETE FROM a USING {$wpdb->terms} as a WHERE 1=1 AND a.term_id IN (%s);";
 			$q = sprintf( $q, $idList );
			$res = $wpdb->query( $q );
			$ret = $res;
			return $ret;
		}
		
		private function attrclean_remove_term_taxonomy( $term_taxonomy_id, $taxonomy, $debug = false ) {
			global $wpdb;
			
			$idList = (is_array($term_taxonomy_id) && count($term_taxonomy_id)>0 ? implode(', ', array_map(array($this->the_plugin, 'prepareForInList'), $term_taxonomy_id)) : 0);

			if ( $debug ) {
			$q = "SELECT a.term_id, a.taxonomy, a.term_taxonomy_id FROM {$wpdb->term_taxonomy} AS a
 WHERE 1=1 AND a.term_taxonomy_id IN (%s) AND a.taxonomy = '%s' ORDER BY a.term_taxonomy_id ASC;";
 			$q = sprintf( $q, $idList, esc_sql($taxonomy) );
			$res = $wpdb->get_results( $q );
			if ( !$res || !is_array($res) ) return false;

			$ret = array();
			$ret[] = 'term_id, taxonomy, term_taxonomy_id';
			foreach ($res as $key => $value) {
				$term_taxonomy_id = $value->term_taxonomy_id;
				$ret["$term_taxonomy_id"] = $value;
			}
			return $ret;
			}

			// execution/ update
			$q = "DELETE FROM a USING {$wpdb->term_taxonomy} as a WHERE 1=1 AND a.term_taxonomy_id IN (%s) AND a.taxonomy = '%s';";
 			$q = sprintf( $q, $idList, $taxonomy );
			$res = $wpdb->query( $q );
			$ret = $res;
			return $ret;
		}

		public function attrclean_clean_all( $retType = 'die' ) {
			// :: get duplicates list
			$duplicates = $this->attrclean_getDuplicateList();
  
			if ( empty($duplicates) || !is_array($duplicates) ) {
				$ret['status'] = 'valid';
				$ret['msg_html'] = __('no duplicate terms found!', $this->the_plugin->localizationName);
				if ( $retType == 'die' ) die(json_encode($ret));
				else return $ret;
			}
			// html message
			$__duplicates = array();
			$__duplicates[] = '0 : name, slug, term_taxonomy_id, taxonomy, count';
			foreach ($duplicates as $key => $value) {
				$__duplicates[] = $value->name . ' : ' . implode(', ', (array) $value);
			}
			$ret['status'] = 'valid';
			$ret['msg_html'] = implode('<br />', $__duplicates);
			// if ( $retType == 'die' ) die(json_encode($ret));
			// else return $ret;

			// :: get terms per duplicate
			$__removeStat = array();
			$__terms = array();
			$__terms[] = '0 : term_id, name, slug, term_taxonomy_id, taxonomy, count';
			foreach ($duplicates as $key => $value) {
				$terms = $this->attrclean_getTermPerDuplicate( $value->name, $value->taxonomy );
				if ( empty($terms) || !is_array($terms) || count($terms) < 2 ) continue 1;

				$first_term = array_shift($terms);

				// html message
				foreach ($terms as $k => $v) {
					$__terms[] = $key . ' : ' . implode(', ', (array) $v);
				}

				// :: remove duplicate term
				$removeStat = $this->attrclean_removeDuplicate($first_term->term_id, $terms, false);
				
				// html message
				$__removeStat[] = '-------------------------------------- ' . $key;
				$__removeStat[] = '---- term kept';
				$__removeStat[] = 'term_id, term_taxonomy_id';
				$__removeStat[] = $first_term->term_id . ', ' . $first_term->term_taxonomy_id;
				foreach ($removeStat as $k => $v) {
					$__removeStat[] = '---- ' . $k;
					if ( !empty($v) && is_array($v) ) {
						foreach ($v as $k2 => $v2) {
							$__removeStat[] = implode(', ', (array) $v2);
						}
					} else if ( !is_array($v) ) {
						$__removeStat[] = (int) $v;
					} else {
						$__removeStat[] = 'empty!';
					}
				}
			}

			$ret['status'] = 'valid';
			$ret['msg_html'] = implode('<br />', $__removeStat);
			if ( $retType == 'die' ) die(json_encode($ret));
			else return $ret;
		}

	}
}