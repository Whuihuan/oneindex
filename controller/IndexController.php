<?php
class IndexController{
    private $url_path;
    private $name;
    private $path;
    private $items;
    private $time;
    
    function __construct(){
        //获取路径和文件名
        $paths = explode('/', rawurldecode($_GET['path']));
        if(!isset($_GET["json"]))
        {
          $key="/".rawurldecode($_GET["path"])."/?json";
          if(isset($_GET[$key]))
          {
            $str=$_GET[$key];
            $value=substr($str,-1) !='/' ? $str : substr($str,0,strlen($str)-1);
            $_GET["json"]=$value;
          }
        }
        else if(!isset($_GET["m3u"]))
        {
          $key="/".rawurldecode($_GET["path"])."/?m3u";
          if(isset($_GET[$key]))
          {
            $str=$_GET[$key];
            $value=substr($str,-1) !='/' ? $str : substr($str,0,strlen($str)-1);
            $_GET["json"]=$value;
          }
        }
        if((!isset($_GET["json"])&&!isset($_GET["m3u"]))&&substr($_SERVER['REQUEST_URI'], -1) != '/'){
            $this->name = array_pop($paths);
        }
        $this->url_path = get_absolute_path(join('/', $paths));
        $this->path = get_absolute_path(config('onedrive_root').$this->url_path);
        //获取文件夹下所有元素
        $this->items = $this->items($this->path);
    }
    
    
    function index(){
        //是否404
        $this->is404();
        
        $this->is_password();
        
        header("Expires:-1");
        header("Cache-Control:no_cache");
        header("Pragma:no-cache");
        
        if(isset($_GET["json"]))
        {
            if($_GET["json"]=="dir")
            {
                return $this->json("dir");
			}
			else if($_GET["json"] == "all")
			{
				return $this->json("all");
			}
            else
            {
                return $this->json();
            }
        }
        else if(isset($_GET["m3u"]))
        {
            return $this->m3u();
        }
        
        if(!empty($this->name)){//file
            return $this->file();
        }else{//dir
            return $this->dir();
        }
    }
    
    //判断是否加密
    function is_password(){
        if(empty($this->items['.password'])){
            return false;
        }else{
            $this->items['.password']['path'] = get_absolute_path($this->path).'.password';
        }
        
        $password = $this->get_content($this->items['.password']);
        list($password) = explode("\n",$password);
        $password = trim($password);
        unset($this->items['.password']);
        if(!empty($password) && strcmp($password, $_COOKIE[md5($this->path)]) === 0){
            return true;
        }
        
        $this->password($password);
        
    }
    
    function password($password){
        if(!empty($_POST['password']) && strcmp($password, $_POST['password']) === 0){
            setcookie(md5($this->path), $_POST['password']);
            return true;
        }
        $navs = $this->navs();
        echo view::load('password')->with('navs',$navs);
        exit();
    }
    
    //文件
    function file(){
        $item = $this->items[$this->name];
        if ($item['folder']) {//是文件夹
            $url = $_SERVER['REQUEST_URI'].'/';
        }elseif(!is_null($_GET['t']) ){//缩略图
            $url = $this->thumbnail($item);
        }elseif($_SERVER['REQUEST_METHOD'] == 'POST' || !is_null($_GET['s']) ){
            return $this->show($item);
        }else{//返回下载链接
            $url = $item['downloadUrl'];
        }
        header('Location: '.$url);
    }
    
    
    
    //文件夹
    function dir(){
        $root = get_absolute_path(dirname($_SERVER['SCRIPT_NAME'])).config('root_path');
        $navs = $this->navs();
        
        if($this->items['index.html']){
            $this->items['index.html']['path'] = get_absolute_path($this->path).'index.html';
            $index = $this->get_content($this->items['index.html']);
            header('Content-type: text/html');
            echo $index;
            exit();
        }
        
        if($this->items['README.md']){
            $this->items['README.md']['path'] = get_absolute_path($this->path).'README.md';
            $readme = $this->get_content($this->items['README.md']);
            $Parsedown = new Parsedown();
            $readme = $Parsedown->text($readme);
            //不在列表中展示
            unset($this->items['README.md']);
        }
        
        if($this->items['HEAD.md']){
            $this->items['HEAD.md']['path'] = get_absolute_path($this->path).'HEAD.md';
            $head = $this->get_content($this->items['HEAD.md']);
            $Parsedown = new Parsedown();
            $head = $Parsedown->text($head);
            //不在列表中展示
            unset($this->items['HEAD.md']);
        }
        return view::load('list')->with('title', 'index of '. urldecode($this->url_path))
        ->with('navs', $navs)
        ->with('path',join("/", array_map("rawurlencode", explode("/", $this->url_path)))  )
        ->with('root', $root)
        ->with('items', $this->items)
        ->with('head',$head)
        ->with('readme',$readme);
    }
    
    // 文件夹/文件列表以json格式显示
    function json($show="file")
    {
		$items = $this->getJson($show);
        return view::load('json')->with('items', $items);
	}
	
	function m3u()
	{
    		$items = $this->getJson("m3u");
        return view::load('m3u')->with('items', $items);
	}
	
	function getJson($show="file",$gpath="",$ref=false)
	{
			$pitems=array();
if($show=="m3u")
			{
        $titems=$this->getJson("all");
        $slhttp = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $url=$slhttp.$_SERVER['SERVER_NAME'].str_replace("?".$_SERVER["QUERY_STRING"],"/",$_SERVER["REQUEST_URI"]);
        foreach($titems as $titem)
        {
          $addr=$url.$titem["name"]."/";
          if(isset($titem["items"]))
          {
            foreach($titem["items"] as $tit)
            {
              $addrb=$addr.$tit["name"]."/";
              if(isset($tit["items"]))
              {
                foreach($tit["items"] as $mu)
                {
                  $addrc=$addrb.rawurlencode($mu["name"]);
                  array_push($pitems,$addrc);
                }
             }
          }
            }
          }
          return $pitems;
          }
		$gpath = ($gpath=="") ? $this->path : $gpath;
        $items = ($ref) ? $this->items($gpath) : $this->items;
        // 如果存在隐藏文件，则不显示该文件夹的内容
        if($items["hidden"])
        {
            return $pitems;
        }
        foreach($items as $item)
        {
            $subitems=array();
            $subitems["name"]=$item["name"];
            if($show=="dir")
            {
                // 非文件夹不显示
                if(!$item["folder"]) continue;
                $sitems=array();
                $spath=get_absolute_path($gpath."/".$item["name"]."/");
                foreach($this->items($spath) as $sitem)
                {
                    $tmparr = array("name" => $sitem["name"]);
                    array_push($sitems,$tmparr);
                }
                $subitems["items"]=$sitems;
            }
			else if($show == "all")
			{
                // 非文件夹不显示
                if(!$item["folder"]) continue;
                $sitems=array();
                $spath=get_absolute_path($gpath."/".$item["name"]."/");
                foreach($this->items($spath) as $sitem)
                {
					$tmparr = array("name" => $sitem["name"]);
					$subpath = get_absolute_path($spath."/".$sitem["name"]."/");
					$fitems = $this->getJson("file",$subpath,true);
					$tmparr["items"] = $fitems;
                    array_push($sitems,$tmparr);
                }
                $subitems["items"]=$sitems;
        }
            array_push($pitems,$subitems);
		}
		return $pitems;
	}
    
    function show($item){
        $root = get_absolute_path(dirname($_SERVER['SCRIPT_NAME'])).(config('root_path')?'?/':'');
        $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        $data['title'] = $item['name'];
        $data['navs'] = $this->navs();
        $data['item'] = $item;
        $data['ext'] = $ext;
        $data['item']['path'] = get_absolute_path($this->path).$this->name;
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $uri = onedrive::urlencode(get_absolute_path($this->url_path.'/'.$this->name));
        $data['url'] = $http_type.$_SERVER['HTTP_HOST'].$root.$uri;
        
        
        $show = config('show');
        foreach($show as $n=>$exts){
            if(in_array($ext,$exts)){
                return view::load('show/'.$n)->with($data);
            }
        }
        
        header('Location: '.$item['downloadUrl']);
    }
    
    //缩略图
    function thumbnail($item){
        if(!empty($_GET['t'])){
            list($width, $height) = explode('|', $_GET['t']);
        }else{
            //800 176 96
            $width = $height = 800;
        }
        $item['thumb'] = onedrive::thumbnail($this->path.$this->name);
        list($item['thumb'],$tmp) = explode('&width=', $item['thumb']);
        $item['thumb'] .= strpos($item['thumb'], '?')?'&':'?';
        return $item['thumb']."width={$width}&height={$height}";
    }
    
    //文件夹下元素
    function items($path, $fetch=false){
        $items = cache::get('dir_'.$path, function(){
            return onedrive::dir($path);
        }, config('cache_expire_time'));
        return $items;
    }
    
    function navs(){
        $root = get_absolute_path(dirname($_SERVER['SCRIPT_NAME'])).config('root_path');
        $navs['/'] = get_absolute_path($root.'/');
        foreach(explode('/',$this->url_path) as $v){
            if(empty($v)){
                continue;
            }
            $navs[rawurldecode($v)] = end($navs).$v.'/';
        }
        if(!empty($this->name)){
            $navs[$this->name] = end($navs).urlencode($this->name);
        }
        
        return $navs;
    }
    
    static function get_content($item){
        $content = cache::get('content_'.$item['path'], function() use ($item){
            $resp = fetch::get($item['downloadUrl']);
            if($resp->http_code == 200){
                return $resp->content;
            }
        }, config('cache_expire_time') );
        return $content;
    }
    
    //是否404
    function is404(){
        if(!empty($this->items[$this->name]) || (empty($this->name) && is_array($this->items)) ){
            return false;
        }
        
        http_response_code(404);
        view::load('404')->show();
        die();
    }
    
    function __destruct(){
        if (!function_exists("fastcgi_finish_request")) {
            return;
        }
    }
}