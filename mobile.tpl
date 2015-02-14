<!DOCTYPE html>
<html>
        <head>
        <title>Page Title</title>

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="stylesheet" href="http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.css" />
        <script src="http://code.jquery.com/jquery-1.8.2.min.js"></script>
        <script src="http://code.jquery.com/mobile/1.2.0/jquery.mobile-1.2.0.min.js"></script>
</head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<body>

<div data-role="page">
<div data-role="header">
		<h1>CI Mobile Upload</h1>
</div><!-- /header -->
<div id="main" data-role="content">
<form enctype="multipart/form-data" data-ajax="false" action="{$smarty.server.SCRIPT_NAME}" method="POST">
<input id="userfile1" name="userfile1" type="file" />
<label for="newname">New name for file</label>
<input id="newname" name="newname" type="text" />
<input value="Upload" type="submit" />
<input name="MAX_FILE_SIZE" value="3000000" type="hidden" />
</form>
 </div><!-- /content -->
</div><!-- /page -->
</body>
</html>

