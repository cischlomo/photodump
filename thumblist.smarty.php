<?php

require 'smarty/Smarty.class.php';

$smarty = new Smarty;

$smarty->debugging = true;
$smarty->caching = false;
//$smarty->cache_lifetime = 120;
$smarty->assign('links',
 array (
  array ('title'=>'Upload','url'=>$_SERVER['PHP_SELF'] . '?upload'),
  array ('title'=>'Mobile Upload','url'=>'upload_mobile.html'),
  array ('title'=>'View All','url'=> $_SERVER['PHP_SELF'] . '?start=0'),
  array ('title'=>'Random','url'=> $_SERVER['PHP_SELF'] . '?random')
  )
 );
 $smarty->configLoad('thumb.conf');
 if (isset($_FILES) && count($_FILES)>0) {
  $smarty->assign('uploadfiles',true);
  $smarty->assign('uploaded_file',showFiles($smarty));
 } else {
  $db=new PDO('sqlite:photo3.db');
  $sth=$db->query("select count(*) from files");
  list ($num)=$sth->fetch();
  $sth->closeCursor();
  $db=null;
  $smarty->assign('total_num_photos', $num);	 
 }

$smarty->registerPlugin("function","nav_arrows","nav_arrows");
$smarty->registerPlugin("function","show_random","show_random");
$smarty->display('thumb.tpl');

function getStart($start,$smarty) {
 $pagination=$smarty->getConfigVars('pagination');
 if (!$start ) $start=0;
 if ($start < 0) $start=0;
 $total_num_photos=$smarty->getTemplateVars('total_num_photos');
 if ($start + $pagination > $total_num_photos) {
  $start=$total_num_photos-$pagination;
 }
 return $start;
}
function show_random ($params,$smarty) {
	$rand = mt_rand(1,$smarty->getTemplateVars('total_num_photos'));
	$photos = queryPhotos("select * from files where rowid=$rand");
	return $photos[0]['name'];
}
function nav_arrows($params, $smarty) {
	$html="";
	$total_num_photos=$smarty->getTemplateVars('total_num_photos');
	$start=$smarty->getTemplateVars('start');
	$pagination=$smarty->getConfigVars('pagination');
	if ($start >0) { //show left
	 $start_left=($start-$pagination < 0  ? 0 : $start-$pagination);
	 $html.='<a href="thumblist.smarty.php?start='.($start_left).'"><img src="'.
	 $smarty->getConfigVars('siteurl').'/'.$smarty->getConfigVars('site_hombe') . '/' . $smarty->getConfigVars('graphicsdir').'/left.gif"></a>';
	}
	if ($start + $pagination < $total_num_photos) { //show right
	 $start_right=( ($start + $pagination*2 > $total_num_photos - 1) ? $total_num_photos-$pagination : $start + $pagination  );
	 $html.='<a href="thumblist.smarty.php?start='.($start_right).'"><img src="'.
	 $smarty->getConfigVars('siteurl').'/'.$smarty->getConfigVars('site_hombe') . '/' .$smarty->getConfigVars('graphicsdir').'/right.gif"></a>';
	}
	return $html;
}

function showFiles($smarty) {
 if (!isset($_FILES) || count($_FILES)<1 || $_FILES['upload']['error']!=UPLOAD_ERR_OK) {
	 $error="something went wrong";
	 return array ('error'=>$error);
 }
 $filename=$_FILES['userfile1']['name'];
 list(,$type)=explode('/',$_FILES['userfile1']['type']);
 $tmp_file=tempnam(sys_get_temp_dir(),"abc");
 copy ($_FILES['userfile1']['tmp_name'],$tmp_file);
 
 $photos=queryPhotos ("select * from files where name='$filename'");
 if (false && count($photos)>0) {
	 $error="photo exists";
	 return array ('error'=>$error);
 }
 if (!check_file_name($filename,$smarty)) {
	 $error="file does not conform to naming standards";
	 return array ('error'=>$error);
 }
 if (!file_exists($tmp_file)) {
	 $error="temp file got deleted before I could access it";
	 return array ('error'=>$error);
 } 
 $filesize=filesize($tmp_file);
 if ($filesize==0 || $filesize===FALSE) {
	return array ('error'=>"zero length file");
 }
 
 if(md5_check($tmp_file)) {
   copy ($smarty->getConfigVars('photodir') . '/rickroll.gif', $smarty->getConfigVars('photodir') . '/' . $filename);
   return array ('filename'=>$filename,'error'=>null); //silently fail
 }
 
 $imagesize=getimagesize($tmp_file);
 if ($imagesize == FALSE) {
	 return array('error'=>"not a valid image");
 }

 $w=$imagesize[0];
 $h=$imagesize[1];

 // no errors
 //resize all except gifs due to animation
 //post processing for jpgs: bartletterize, iphone orient
 //create thumb
 //1
 if ($type=='jpeg') {
  iphone_orient($tmp_file);//have to do this first before exif gets clobbered
 }
 $filesize=filesize($tmp_file);
 
 //2
 $resize_above=$smarty->getConfigVars('resize_above');
 if ($type!='gif' && $filesize>$resize_above) {
  reduce_filesize($tmp_file,$w,$h,$type,$resize_above);
 }
 
 //3
 if ($type=='jpeg') {
  //Bartlettify
  move_to_bartlett($tmp_file);
 }
 
 
 copy ($tmp_file,$smarty->getConfigVars('photodir') . '/' . $filename);
 
 //4
 make_thumb($tmp_file,$w,$h,$type, $smarty);
 copy ($tmp_file,$smarty->getConfigVars('thumbdir') . "/thb_$filename");
 
 $db=new PDO('sqlite:photo3.db');
 $sql="insert into files (name,date) values ('$filename',strftime('%s', 'now'))";
 //error_log($sql);
 $db->exec($sql);
 $db=null;
 //error_log("mime type: " . print_r( $imagesize,1));
 return array ('filename'=>$filename,'error'=>null);
}

function queryPhotos($sql){
 $filez=array();
 if (!isset($sql)) return NULL;
 $db=new PDO('sqlite:photo3.db');
 $sth=$db->query($sql);
 while(($row=$sth->fetch(PDO::FETCH_ASSOC)) && $filez[]=$row);
 $sth->closeCursor();
 $db=null;
 return $filez;
}

function get_mime_type($file) {
	$mtype = false;
	if (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mtype = finfo_file($finfo, $file);
		finfo_close($finfo);
	} elseif (function_exists('mime_content_type')) {
		$mtype = mime_content_type($file);
	} 
	return $mtype;
}

function check_file_name($the_name,$smarty) {
	if ($the_name != '') {
		if (strlen($the_name) > $smarty->getConfigVars('max_length_filename')) {
			//$this->message[] = $this->error_text(13);
			return false;
		} else {
			if (preg_match('/^([a-z0-9_\-]*\.?)\.[a-z0-9]{1,5}$/i', $the_name)) { // v. 2.34 fixed the pattern
				return true;
			} else {
				//$this->message[] = $this->error_text(12);
				return false;
			}
		}
	} else {
		//$this->message[] = $this->error_text(10);
		return false;
	}
}

function move_to_bartlett($filename) {
	$bartlett = array(
			 0xff, 0xe1, //APP1 
			 0x00, 0xA4, //size, 2nd octet was b8 
			 0x45, 0x78, 0x69, 0x66, 0x00, 0x00, //exif prolog 
			 0x49, 0x49,  //motorola endian
			 0x2a, 0x00, //42
			 0x08, 0x00, 0x00, 0x00, // offset of 0th ifd

			 //0th ifd
			 0x01, 0x00, //IOP# num ifds to follow
			 0x25, 0x88, //gps tag 
			 0x04, 0x00, //type long
			 0x01, 0x00, 0x00, 0x00, //count
			 0x1A, 0x00, 0x00, 0x00, //offset of gps ifd
			 0x00, 0x00, 0x00, 0x00, //next ifd offset
			 
			 0x06, 0x00, //num GPS ifd's 
			 
			 0x00, 0x00, // tag: version 
			 0x01, 0x00, // type: 8 bits 
			 0x04, 0x00, 0x00, 0x00, //count 
			 0x02, 0x02, 0x00, 0x00, //value
			 
			 0x06, 0x00, // altitude tag 
			 0x05, 0x00, //type rational 
			 0x01, 0x00, 0x00, 0x00, //count 
			 0x64, 0x00, 0x00, 0x00, //offset 

			 0x02, 0x00, //latitude tag 
			 0x05, 0x00, //type rational 
			 0x03, 0x00, 0x00, 0x00, //count 
			 0x6C, 0x00, 0x00, 0x00, //offset 
			 
			 
			 0x04, 0x00, //longitude tag 
			 0x05, 0x00, //type rational 
			 0x03, 0x00, 0x00, 0x00, //count
			 0x84, 0x00, 0x00, 0x00, //offset 
			 
			 
			 0x01, 0x00, //latref tag
			 0x02, 0x00, //ascii type
			 0x02, 0x00, 0x00, 0x00, //count 
			 0x4e, 0x00, 0x00, 0x00, //"N" 
			 
			 0x03, 0x00, //longref tag
			 0x02, 0x00, //ascii type
			 0x02, 0x00, 0x00, 0x00, //count 
			 0x57, 0x00, 0x00, 0x00, // "W" 
			 
			 0x00, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, //altitude 

 			 0x2A, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, //lat 42.019824 42/1,1/1,11/1
                         0x1, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00,
                         0xB, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00,

                         0x58, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, //long 88.202322 88/1,12/1,8/1
                         0xC, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00,
                         0x8, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00);

//decimalToDMS
$tmp_file=tempnam(sys_get_temp_dir(),"def");

$found_oldexif=FALSE;
$in_stream=fopen($filename,"rb");
$out_stream=fopen($tmp_file,"wb");

//read 2 bytes
$buffer=fread($in_stream,2);
fwrite($out_stream,$buffer,2);			
$thebyte=0;
$totalbytesread=0;
foreach ($bartlett as $bartlettbyte) {
	fwrite($out_stream,chr($bartlettbyte),1);
}
while (!feof($in_stream) && $found_oldexif==FALSE ){
//should have found any exif by ...
	$thebyte=fread($in_stream,1);
	if(ord($thebyte)==0xff) {
		//error_log ("hit ff");
		if ((ord($thebyte=fread($in_stream,1)))==0xe1) {
			//error_log ("hit ff e1");
			//skip the next n bytes where n=..
			$lh=0;
				$ll=0;
			$lh=ord(fread($in_stream,1));
			$ll=ord(fread($in_stream,1));
			$itemlen = ($lh << 8) | $ll;
			//error_log("itemlen is $itemlen");
			$discard=fread($in_stream,($itemlen-2)); //-2 cause already 2 in
			$found_oldexif=TRUE;
			} else {
			 fwrite($out_stream,chr(0xff));
			 fwrite($out_stream,$thebyte);
			$totalbytesread+=2;
			}
		} else {
		fwrite($out_stream,$thebyte,1);
		if ($totalbytesread++>4096) {
			//error_log("hit 4096 bytes");
			break;
		}
	}
}//end while 
//read the rest

 while (!feof($in_stream)) {
  $buffer=fread($in_stream,8192);
  fwrite($out_stream, $buffer);
 }
 fclose ($in_stream);
 fclose ($out_stream);
 copy ($tmp_file,$filename);
 @unlink($tmp_file);
 return TRUE;
}


function iphone_orient($filename) {
 $tmp_file=tempnam(sys_get_temp_dir(),"def");
 $in_stream=fopen($filename,"rb");
 $str=fread($in_stream,2);
 fclose($in_stream);
 if (ord($str[0])==0xff && ord($str[1])==0xd8) { //jpeg
 } else {
  return;
 }
 $exif=exif_read_data($filename);
 if (!$exif || !isset($exif['Orientation'])) return;
 $image_p=imagecreatefromjpeg($filename);
 $orientation=$exif['Orientation'];
 //echo "orientation: $orientation" ;
 switch($orientation) {
        case 3:
            $image_p = imagerotate($image_p, 180, 0);
            imagejpeg($image_p,$tmp_file);
			copy ($tmp_file,$filename);
            break;
        case 6:
            $image_p = imagerotate($image_p, -90, 0);
            imagejpeg($image_p,$tmp_file);
			copy ($tmp_file,$filename);
            break;
        case 8:
            $image_p = imagerotate($image_p, 90, 0);
            imagejpeg($image_p,$tmp_file);
			copy ($tmp_file,$filename);
            break;
		default:
		}
}

//reduce_filesize($tmp_file,$w,$h,$type,$resize_above);
function reduce_filesize($filename,&$w,&$h,$type,$resize_above) {
 $tmp_file=tempnam(sys_get_temp_dir(),"def");
 $reduce_ratio=0.9;
 $filesize=filesize($filename);
 ini_set("memory_limit", -1);
 while ($reduce_ratio>.1 && $filesize>$resize_above){
  $nw=intval($w*$reduce_ratio);
  $nh=intval($h*$reduce_ratio);
  $img=NULL;
  $cmd="\$img=imagecreatefrom".$type."(\$filename);";
  eval($cmd) ;
  $newimg=imagecreatetruecolor($nw,$nh);
  imagecopyresized ($newimg,$img,0,0,0,0,$nw,$nh,$w,$h);
  @unlink($tmp_file);
  $cmd="image".$type."(\$newimg,\$tmp_file);";
  eval($cmd) ;
  $filesize=filesize($tmp_file);
  error_log("filesize: $filesize resize_above: $resize_above");
  $reduce_ratio-=.1;
 }
 if ($nw>0) $w=$nw;
 if ($nh>0) $h=$nh;
 if ($nw>0 || $nh>0) { // if resizing took place replace old file with resized file
  copy ($tmp_file,$filename);
 }
}

function make_thumb($filename,$w,$h,$type,$smarty) {
 //make thumb? resize to "thumbsize"
 $tmp_file=tempnam(sys_get_temp_dir(),"def");
 $largest_dim = ($w > $h ? $w : $h);
 $thumbsize=intval($smarty->getConfigVars('thumbsize'));
 if ($largest_dim>$thumbsize) {
	$reduce_ratio=$thumbsize/$largest_dim;
    $nw=intval($w*$reduce_ratio);
    $nh=intval($h*$reduce_ratio);
    $img=NULL;
    $cmd="\$img=imagecreatefrom".$type."(\$filename);";
	error_log("nw: $nw nh: $nh, reduce ratio: $reduce_ratio  w: $w  h: $h");
    eval($cmd) ;
    $newimg=imagecreatetruecolor($nw,$nh);
    imagecopyresized ($newimg,$img,0,0,0,0,$nw,$nh,$w,$h);
	$cmd="image".$type."(\$newimg,\$tmp_file);";
    eval($cmd);
	copy ($tmp_file,$filename);
 }
}

function deletephoto($filename){
	global $smarty;
	copy ($smarty->getConfigVars('photodir') . '/rageguy.jpg', $smarty->getConfigVars('photodir') . '/' . $filename);
	@unlink($smarty->getConfigVars('thumbdir') . '/thb_' . $filename);
}
function banphoto($filename){
	global $smarty;
	copy ($smarty->getConfigVars('photodir') . '/rageguy.jpg', $smarty->getConfigVars('photodir') . '/' . $filename);
	@unlink($smarty->getConfigVars('thumbdir') . '/thb_' . $filename);
}

function md5_check($filename){
 $md5s=file('md5s',FILE_IGNORE_NEW_LINES);
 $md5_f=md5_file($filename);
 error_log ("md5_f: $md5_f");
 if (in_array($md5_f,$md5s)) {
  error_log("gotcha $filename");
  return TRUE;
 }
 return FALSE;
}

?>