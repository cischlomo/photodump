{include file="header.tpl"}
<center>
 {section name=links loop=$links}
  <a href="{$links[links].url}">{$links[links].title}</a> | 
 {/section}
 <br>
 {include file="search.tpl"}
  or email pics as an attachment to pics@campidiot.com<br>
  
  
 {* ----------------------------------------random------------------------------------------------- *}
 {if isset($smarty.get.random)}
  <img src="{#siteurl#}/{#site_hombe#}/{#photodir#}/{show_random}">
  
  
 {* -----------------------------------------search------------------------------------------------ *}
 {elseif isset($smarty.get.search)}
  {$photos = queryPhotos("select * from files where name like '%{$smarty.get.search}%'")}
  {section name=photos loop=$photos}
   <a href="{$smarty.server.SCRIPT_NAME}?show={$photos[photos].name}">{$photos[photos].name}</a><br>
  {/section}
  
  
 {* -----------------------------------------show single------------------------------------------------ *}
 {elseif isset($smarty.get.show)}
  <img src="{#siteurl#}/{#site_hombe#}/{#photodir#}/{$smarty.get.show}">
  {if isset($smarty.get.delete) }
   {if $smarty.get.delete == "0" }
    {deletephoto("{$smarty.get.show}") }
    <BR>PHOTO DELETED
   {elseif $smarty.get.delete == "1"}
    {banphoto("{$smarty.get.show}")}
    <BR>PHOTO BANNED
   {/if}
  {/if}
  
  
 {* -----------------------------------------upload------------------------------------------------ *}
 {elseif isset($smarty.get.upload)}
  <p><b>Upload</b></p>
  <!-- The data encoding type, enctype, MUST be specified as below --> 
  <form enctype="multipart/form-data" action="{$smarty.server.SCRIPT_NAME}" method="POST">
  <input id="userfile1" name="userfile1" type="file" />
  <input name="MAX_FILE_SIZE" value="30000000" type="hidden" />
  <br />
  <br /><input value="upload" type="submit" />
  </form>
  
  
 {* -----------------------------------------post upload------------------------------------------------ *}
 {elseif $uploadfiles}
  {if $uploaded_file.error}
   <br><br><br>File upload failed: {$uploaded_file.error}
  {else}
   <br><br><br>File Successfully Uploaded.<br>
   <a href="{$smarty.server.SCRIPT_NAME}?show={$uploaded_file.filename}">{$uploaded_file.filename}</a>
  {/if}
  
	
 {* -----------------------------------------browse------------------------------------------------ *}
 {else}
  {$start = getStart($smarty.get.start, $smarty.template_object) }
  {$photos = queryPhotos("select * from files order by date desc limit $start,{#pagination#}")}
  {nav_arrows}
  {section name=photos loop=$photos}
   <p><a href="{$smarty.server.SCRIPT_NAME}?show={$photos[photos].name}">
   {if file_exists("{#thumbdir#}/thb_{$photos[photos].name}")}  {* --- display thumbnail if available --- *}
    <img src="{#siteurl#}/{#site_hombe#}/{#thumbdir#}/thb_{$photos[photos].name}"></a></p>
   {else}
    <img src="{#siteurl#}/{#site_hombe#}/{#photodir#}/{$photos[photos].name}"></a></p>
   {/if}
  {/section}
  {nav_arrows}
 {/if}
</center>
{include file="footer.tpl"}
