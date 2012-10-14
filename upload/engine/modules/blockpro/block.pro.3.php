<?
/*
=============================================================================
BlockPro 3 - ������ ��� ������ ������ � ��������� �� ��������� ����� DLE (������������ �� 9.7)
=============================================================================
����� ������: �������� 
URL: http://blockpro.ru/
ICQ: 817233 
email: pafnuty10@gmail.com
-----------------------------------------------------------------------------
����� �������: ��������� �����
URL: http://mithrandir.ru/
email: mail@mithrandir.ru
=============================================================================
����:  block.pro.3.php
-----------------------------------------------------------------------------
������: 3.0a 
=============================================================================
*/

//��� ������ ������� ������)))
if( ! defined( 'DATALIFEENGINE' ) ) {
	//����� ���������� ����� ������)))
	die( '<iframe width="853" height="480" style="margin: 50px;" src="http://www.youtube.com/embed/mTQLW3FNy-g" frameborder="0" allowfullscreen></iframe>' );
}

if(!class_exists('BlockPro')) {
	class BlockPro {

		//����������� ������� ������
		public function __construct($BlockProConfig)
		{
			// ���������� DLE_API
			global $db, $config, $category;
			include ('engine/api/api.class.php');
			$this->dle_api = $dle_api;
			
			// ������ ������������ ������
			$this->config = $BlockProConfig;
		}

		/*
		 * ������� ����� ������ BlockPro
		 */
		public function runBlockPro()
		{
			if ($this->config['cache_live']) {
				$this->config['prefix'] = ''; //���� ����������� ����� ���� ���� - ������� ������� news_ ����� ��� �� �������� ���������
			}

			// ������� ���������� ���������� ������ �� ����
			$output = false;

			if( !$this->config['nocache'])
			{
				$output = $this->dle_api->load_from_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)));
			}
			
			// ���� �������� ���� ��� ������ ������������ ��������, ������� ���������� ����
			if($output !== false)
			{
				$this->showOutput($output);
				return;
			}
			
			// ���� � ���� ������ �� �������, ���������� ������ ������

			$wheres = array();

			/**
			 * Service function - take params from table
			 * @param $table string - �������� �������
			 * @param $fields string - ����������� ���� ����� ������� ��� * ��� ����
			 * @param $where string - ������� �������
			 * @param $multirow bool - �������� �� ���� ��� ��� ���������
			 * @param $start int - ��������� �������� �������
			 * @param $limit int - ���������� ������� ��� �������, 0 - ������� ���
			 * @param $sort string - ����, �� �������� �������������� ����������
			 * @param $sort_order - ����������� ����������
			 * @return array � ������� ��� false ���� mysql ������� 0 �����
			 */
			//$news = $this->dle_api->load_table (PREFIX."_post", $fields = "*", $where = '1', $multirow = false, $start = 0, $limit = 10, $sort = '', $sort_order = 'desc');

			$news = $this->dle_api->load_table (PREFIX."_post", "*", '1', true, 0, 10, 'date', 'desc');


			if(empty($news)) $news = array();

			//��������� �� ������� � ��������� � ��������� ������
			$output = '';
			foreach ($news as $key => $newsItem) {
				// $newsItem

				$image = '';
				switch($this->config['image'])
                {
                    // ����������� �� ��������������� ����
                    case 'xfield':

                        $xfields = xfieldsdataload($newsItem['xfields']);
                        if(!empty($xfields) && !empty($xfields[$this->config['image']]))
                        {
                            $image = getImage($xfields[$this->config['image']]);
                        }
                        break;
                    
                    // ������ ����������� �� ������� ��������
                    case 'full_story':
                        $image = $this->getImage($newsItem['full_story'], 0);
                        break;
                    
                    // �� ��������� - ������� �������
                    default:
                    	$image = $this->getImage($newsItem['short_story'], 0);
                        break;
                }
                if ($image == '') {
                	$image = "/templates/".$this->dle_api->dle_config['skin']."/images/".$this->config['noimage'];
                }


				/**
				 * �������� ��� ����������� �������
				 */

				$output .= $this->applyTemplate($this->config['template'],
					array(
						'{title}'          	=> $newsItem["title"],
						'{full-link}'		=> $this->getPostUrl($newsItem),
						'{image}'			=> $image,
						// '{description}'   => $description,
					),
					array(
						// "'\[show_name\\](.*?)\[/show_name\]'si" => !empty($name)?"\\1":'',
						// "'\[show_description\\](.*?)\[/show_description\]'si" => !empty($description)?"\\1":'',
					)
				);
			}

			// C�������� � ��� �� ������ ������������
			if(!$this->config['nocache'])
			{
				$this->dle_api->save_to_cache($this->config['prefix'].'bp_'.md5(implode('_', $this->config)), $output);
			}
			
			// ������� ���������� ������
			$this->showOutput($output);
		}

		/**
		 * @param $post - ������ � ����������� � ������
		 * @return string URL ��� ���������
		 */


		public function getImage($post)
		{
			$dir = ROOT_DIR . '/uploads/blockpro/'; //����� ����� ��� ��������
			if(!is_dir($dir)){						//������ � ��������� �����, ���� ��� �������
				@mkdir($dir, 0755);
				@chmod($dir, 0755);
			} 
			if(!chmod($dir, 0755)) {
				@chmod($dir, 0755);
			}

			if(preg_match_all('/<img(?:\\s[^<>]*?)?\\bsrc\\s*=\\s*(?|"([^"]*)"|\'([^\']*)\'|([^<>\'"\\s]*))[^<>]*>/i', $post, $m)) {

				$url = $m[1][0]; 										//����� ������ �������� � �������
				$imgOriginal = str_ireplace('/thumbs', '', $url); 		//���������� ��������, �� ������ ���� ��������� ���� �� �������� ������, ��� thumb � �������

				if ($this->config['img_size']) { 						//���� ���� �������� img_size - �������� ������� ��������
					
					// >>>> ��� ��� ����� ���� ����� ����������

					$urlShort = explode('/uploads/', $url); 			//��������� �� ��� �����, ���� ������ ��� ������
					if(count($urlShort) != 2) continue; 				// �� �� �����, ���� � ���������� ������ uploads					
					$urlShort = ROOT_DIR . '/uploads/' . $urlShort[1]; 	//���� ������ ����� � ����������� root_dir, ���� �������� ������ �� ��������� ��������		
					//if(!is_file($urlShort))  continue; 				//� ���� �� ���? (���� �����������, ����� ������ �� �����)

					// <<<<< ��� ��� ����� ���� ����� ����������

					$fileName = $this->config['img_size']."_".strtolower(basename($urlShort)); 	//���������� ����� ��� �����
					
					if(!file_exists($dir.$fileName)) { //���� �������� ��� - ������ �
						$img_size = explode('x', $this->config['img_size']); 						//��������� ������ � ������

						require_once ENGINE_DIR.'/modules/blockpro/resize_class.php'; 				//��������� ���������� ����� ��� ��������(?), � �� �� �����, ������� � DLE
						$resizeImg = new resize($urlShort);
						$resizeImg -> resizeImage($img_size[0], $img_size[1], $this->config['resize_type']); //�����: exact, portrait, landscape, auto, crop
						//@TODO �������� ����� ����� ������� ����� ������ �����������
						$resizeImg -> saveImage($dir.$fileName); //��������� �������� � ����� /uploads/blockpro
					
					}					 									
					
					$data = $this->dle_api->dle_config['http_home_url']."uploads/blockpro/".$fileName;					
				
				} else {
					$data = $url;
				}

				 // echo "<pre class='orange'>"; print_r("/templates/".$this->dle_api->dle_config['skin']."/images/".$this->config['noimage']); echo "</pre>"; 

				return $data;
			}
			
		}

		/*
		 * @param $post - ������ � ����������� � ������
		 * @return string URL ��� ���������
		 */
		public function getPostUrl($post)
		{
			if($this->dle_api->dle_config['allow_alt_url'] == 'yes')
			{
				if(
					($this->dle_api->dle_config['version_id'] < 9.6 && $post['flag'] && $this->dle_api->dle_config['seo_type'])
						||
					($this->dle_api->dle_config['version_id'] >= 9.6 && ($this->dle_api->dle_config['seo_type'] == 1 || $this->dle_api->dle_config['seo_type'] == 2))
				)
				{
					if(intval($post['category']) && $this->dle_api->dle_config['seo_type'] == 2)
					{
						$url = $this->dle_api->dle_config['http_home_url'].get_url(intval($post['category'])).'/'.$post['id'].'-'.$post['alt_name'].'.html';
					}
					else
					{
						$url = $this->dle_api->dle_config['http_home_url'].$post['id'].'-'.$post['alt_name'].'.html';
					}
				}
				else
				{
					$url = $this->dle_api->dle_config['http_home_url'].date("Y/m/d/", strtotime($post['date'])).$post['alt_name'].'.html';
				}
			}
			else
			{
				$url = $this->dle_api->dle_config['http_home_url'].'index.php?newsid='.$post['id'];
			}

			return $url;
		}

		/*
		 * ����� ������������ tpl-������, �������� � �� ���� � ���������� ����������������� ������
		 * @param $template - �������� �������, ������� ����� ���������
		 * @param $vars - ������������� ������ � ������� ��� ������ ���������� � �������
		 * @param $blocks - ������������� ������ � ������� ��� ������ ������ � �������
		 *
		 * @return string tpl-������, ����������� ������� �� ������� $data
		 */
		public function applyTemplate($template, $vars = array(), $blocks = array())
		{
			// ���������� ���� ������� $template.tpl, ��������� ���
			$tpl = new dle_template();
			$tpl->dir = TEMPLATE_DIR;
			$tpl->load_template($template.'.tpl');

			// ��������� ������ �����������
			foreach($vars as $var => $value)
			{
				$tpl->set($var, $value);
			}

			// ��������� ������ �������
			foreach($blocks as $block => $value)
			{
				$tpl->set_block($block, $value);
			}

			// ����������� ������ (��� �� ��� �� �������� ;))
			$tpl->compile($template);

			// ������� ���������
			return $tpl->result[$template];
		}


		/*
		 * ����� ������� ���������� ������ � �������
		 * @param $output - ������ ��� ������
		 */
		public function showOutput($output)
		{
			echo $output;
			echo "<hr>";
			// echo "<pre class='orange'>"; print_r($output); echo "</pre>";
			// echo "<hr>";
		}

	}//����� ������ BlockPro
} 

	// ������� ������ ������
	$BlockProConfig = array(
		'template'		=> !empty($template)?$template:'blockpro/blockpro', 	//�������� ������� (��� ����������)
		'prefix'		=> !empty($BpPrefix)?$BpPrefix:'news_', 				//��������� ������� ����
		'nocache'		=> !empty($nocache)?$nocache:false,						//�� ������������ ���
		'cache_live'	=> !empty($cache_live)?$cache_live:false,				//����� ����� ����
		'news_num'		=> !empty($news_num)?$news_num:'10',					//���������� �������� � �����
		'img_size'		=> !empty($img_size)?$img_size:false,					//������ ����������� ����� ��������
		'resize_type'	=> !empty($resize_type)?$resize_type:'auto',			//����� ���������� ����� �������� (exact, portrait, landscape, auto, crop)
		'noimage'		=> !empty($noimage)?$noimage:'noimage.png',				//��������-��������
	);
	
	// ������� ��������� ������ ��� ������������ � ��������� ��� ������� �����
	$BlockPro = new BlockPro($BlockProConfig);
	$BlockPro->runBlockPro();

?>