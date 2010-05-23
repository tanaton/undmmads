<?php

class unDMMads
{
	// アフィID
	protected	$afi_id			= 'unkar-001';
	// データ保存フォルダ
	protected	$data_folder	= 'undmmads/data';
	// 画像保存フォルダ
	protected	$img_folder		= 'undmmads/img';

	// サムネイルの最大横幅
	protected	$img_max_width	= 160;
	// サムネイルの最大縦幅
	protected	$img_max_height	= 160;
	// サムネイルのjpeg圧縮率
	protected	$img_quality	= 80;
	// 保存するサムネイルのパスに付与される文字
	protected	$img_thumbnail	= 'thumb';

	// 取得するURL
	protected	$data_list = array(
		array(
			'url'			=> 'http://www.dmm.co.jp/digital/pcgame2/-/list/=/sort=ranking/rss=create/_jloff=1/',
			'cache_time'	=> 86400
		),
		array(
			'url'			=> 'http://www.dmm.co.jp/digital/pcgame/-/list/=/sort=ranking/rss=create/_jloff=1/',
			'cache_time'	=> 86400
		),
		array(
			'url'			=> 'http://www.dmm.co.jp/mono/doujin/-/list/=/media=comic/reserve=mix/sort=date/rss=create/_jloff=1/',
			'cache_time'	=> 86400
		)
	);

	protected	$req_time		= 0;
	protected	$request_type	= 0;

	const		REQUEST_TYPE_CURL			= 1;
	const		REQUEST_TYPE_NORMAL			= 2;
	const		REQUEST_TYPE_CURL_MULTI		= 3;

	public function __construct(array $data_list = array(), $afi_id = '', $request_type = unDMMads::REQUEST_TYPE_CURL)
	{
		if($afi_id != ''){
			$this->afi_id = $afi_id;
		}
		if(!empty($data_list)){
			$this->setRssUrlList($data_list);
		}
		$this->request_type = $request_type;
		$this->req_time = $_SERVER['REQUEST_TIME'];
	}

	public function setRssUrlList(array $data_list)
	{
		$flag = true;
		$this->data_list = array();
		foreach($data_list as $key => $data){
			if(isset($data['url']) && preg_match('/^http:\/\//', $data['url'])){
				if(isset($data['cache_time'])){
					$this->data_list[] = $data;
				} else {
					$flag = false;
				}
			} else {
				$flag = false;
			}
		}
		return $flag;
	}

	public function getRss()
	{
		// 取得するxmlのリストを更新
		$url_list = $this->xmlCacheCheck();
		if(empty($url_list)) return false;
		// 複数取得
		$list = $this->multiRequest($url_list);
		foreach($list as $key => $xmlstr){
			file_put_contents($this->getXMLPath($url_list[$key]), $xmlstr);
			$xml = new SimpleXMLElement($xmlstr);
			$this->getXMLImage($xml);
		}
		return true;
	}

	public function _print($size = 5)
	{
		$this->getRss();
		$list = $this->dataList();
		if(empty($list)) return false;
		shuffle($list);
		echo "<ul>\n";
		for($i = 0; $i < $size; $i++){
			$item = $list[$i];
			echo '<li><a href="', $item['link'], '" target="_blank">', "\n";
			echo '<img src="', $item['thumbnail'], '" alt="', $item['title'], '" />', "\n";
			echo '<br />', $item['title'], '</a></li>', "\n";
		}
		echo "</ul>\n";
	}

	protected function dataList()
	{
		$xml_list = $this->getXMLPathList();
		$list = array();
		foreach($xml_list as $key => $xml_path){
			$xml = new SimpleXMLElement($xml_path, LIBXML_COMPACT, true);
			if(isset($xml->item)){
				foreach($xml->item as $key2 => $item){
					$data = array();
					if(isset($item->title) && isset($item->link) && isset($item->package)){
						$data['title'] = htmlspecialchars($item->title);
						$data['link'] = $item->link.$this->afi_id;
						$img_path = $this->getImagePath($this->getLargeImageUrl($item->package));
						$img_thumb_path = $this->getThumbnailPath($img_path);
						if(file_exists($img_path) && file_exists($img_thumb_path)){
							$data['package'] = $img_path;
							$data['thumbnail'] = $img_thumb_path;
						} else {
							continue;
						}
					} else {
						continue;
					}
					if(isset($item->description)){
						$data['description'] = $item->description;
					} else {
						$data['description'] = '';
					}
					$list[] = $data;
				}
			}
		}
		return $list;
	}

	protected function xmlCacheCheck()
	{
		$url_list = array();
		if(!file_exists($this->data_folder)) return $url_list;
		foreach($this->data_list as $key => $data){
			$path = $this->getXMLPath($data['url']);
			if(file_exists($path)){
				if($data['cache_time'] > 0){
					$xml_time = filemtime($path);
					$req_time = $this->req_time;
					if(($xml_time + $data['cache_time']) <= $req_time){
						$url_list[] = $data['url'];
					}
				}
			} else {
				$url_list[] = $data['url'];
			}
		}
		return $url_list;
	}

	protected function imageCacheCheck(SimpleXMLElement $xml)
	{
		$url_list = array();
		if(!file_exists($this->img_folder)) return $url_list;
		if(isset($xml->item)){
			foreach($xml->item as $key => $item){
				if(isset($item->package)){
					$img_url = $this->getLargeImageUrl($item->package);
					if(!file_exists($this->getImagePath($img_url))){
						$url_list[] = $img_url;
					}
				} else {
					// ダミー画像生成
				}
			}
		}
		return $url_list;
	}

	protected function getXMLPath($url)
	{
		return $this->data_folder.'/'.md5($url).'.xml';
	}

	protected function getImagePath($url)
	{
		return $this->img_folder.'/'.$this->getBasename($url);
	}

	protected function getXMLPathList()
	{
		$xml_list = array();
		if(!file_exists($this->data_folder)) return $xml_list;
		foreach($this->data_list as $key => $data){
			$xml_path = $this->getXMLPath($data['url']);
			if(file_exists($xml_path)){
				$xml_list[] = $xml_path;
			}
		}
		return $xml_list;
	}

	protected function getXMLImage(SimpleXMLElement $xml)
	{
		$url_list = $this->imageCacheCheck($xml);
		if(empty($url_list)) return false;
		$images = $this->multiRequest($url_list);
		$length = count($images);
		for($i = 0; $i < $length; $i++){
			$folder = $this->getImagePath($url_list[$i]);
			file_put_contents($folder, $images[$i]);
			$this->createThumbnail($folder);
		}
	}

	protected function createThumbnail($image_path)
	{
		if(!file_exists($image_path)){
			return false;
		}
		list($img_width, $img_height, $type) = getimagesize($image_path);
		$scale = 1;
		if(($img_width > 0) && ($img_height > 0)){
			$scale = min($this->img_max_width / $img_width, $this->img_max_height / $img_height);
		}
		if($scale < 1){
			$width = floor($scale * $img_width);
			$height = floor($scale * $img_height);
		} else {
			$width = $img_width;
			$height = $img_height;
		}
		$image_resized = imagecreatetruecolor($width, $height);
		if($type === 1){
			$image_orig = imagecreatefromgif($image_path);
		} else if($type === 2){
			$image_orig = imagecreatefromjpeg($image_path);
		} else if($type === 3){
			$image_orig = imagecreatefrompng($image_path);
		} else {
			return false;
		}
		imagecopyresampled($image_resized, $image_orig, 0, 0, 0, 0, $width, $height, $img_width, $img_height);
		// jpegで圧縮保存
		imagejpeg($image_resized, $this->getThumbnailPath($image_path), $this->img_quality);
		// メモリ開放
		imagedestroy($image_resized);
	}

	protected function getLargeImageUrl($url)
	{
		$basename = $this->getBasename($url);
		$image_url = str_replace($basename, '', $url).'/'.str_replace('pt.', 'pl.', $basename);
		return $image_url;
	}

	protected function getBasename($url)
	{
		$attr = parse_url($url);
		$basename = basename($attr['path']);
		return $basename;
	}

	protected function getThumbnailPath($path)
	{
		$info = pathinfo($path);
		$new_path = $info['dirname'].'/'.$info['filename'].'-'.$this->img_thumbnail.'.'.$info['extension'];
		return $new_path;
	}

	protected function multiRequest(array $url_list)
	{
		$ret = array();
		switch($this->request_type){
		case unDMMads::REQUEST_TYPE_CURL:
			$ret = $this->curlRequest($url_list);
			break;
		case unDMMads::REQUEST_TYPE_NORMAL:
			$ret = $this->normalRequest($url_list);
			break;
		case unDMMads::REQUEST_TYPE_CURL_MULTI:
			// 多重リクエスト(最速＆危険)
			$ret = $this->curlMultiRequest($url_list);
			break;
		default:
			// 何もしない
		}
		return $ret;
	}

	protected function curlRequest(array $url_list)
	{
		$result = array();
		// 初期化
		$curl = curl_init();
		foreach($url_list as $key => $url){
			$opt = array();
			$opt[CURLOPT_URL] = $url;
			$opt[CURLOPT_HEADER] = 0;
			$opt[CURLOPT_ENCODING] = 'gzip';
			// 文字列で取得
			$opt[CURLOPT_RETURNTRANSFER] = true;
			// タイムアウトを設定
			$opt[CURLOPT_TIMEOUT] = 10;
			// 400以上のステータスが帰ってきたら本文は取得しない
			$opt[CURLOPT_FAILONERROR] = true;

			// 配列から設定
			curl_setopt_array($curl, $opt);
			$data = curl_exec($curl);
			if($data !== false){
				$result[] = $data;
			}
		}
		curl_close($curl);
		return $result;
	}

	protected function normalRequest(array $url_list)
	{
		$result = array();
		foreach($url_list as $key => $url){
			$data = file_get_contents($url);
			if($data !== false){
				$result[] = $data;
			}
		}
		return $result;
	}

	protected function curlMultiRequest(array $url_list)
	{
		$curly = array();
		$result = array();
		$running = null;
		$mh = curl_multi_init();
		foreach($url_list as $key => $url){
			$opt = array();
			$opt[CURLOPT_URL] = $url;
			$opt[CURLOPT_HEADER] = 0;
			$opt[CURLOPT_ENCODING] = 'gzip';
			// 文字列で取得
			$opt[CURLOPT_RETURNTRANSFER] = true;
			// タイムアウトを設定
			$opt[CURLOPT_TIMEOUT] = 10;
			// 400以上のステータスが帰ってきたら本文は取得しない
			$opt[CURLOPT_FAILONERROR] = true;

			// 初期化
			$curly[$key] = curl_init();
			curl_setopt_array($curly[$key], $opt);
			curl_multi_add_handle($mh, $curly[$key]);
		}
		do {
			// 取得できるまでループ
			usleep(10000);
			curl_multi_exec($mh, $running);
		} while($running > 0);
		foreach($curly as $key => $c){
			$data = curl_multi_getcontent($c);
			if($data !== false){
				$result[] = $data;
			}
			// curlを開放
			curl_multi_remove_handle($mh, $c);
		}
		curl_multi_close($mh);
		return $result;
	}
}

?>
