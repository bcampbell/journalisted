<?php
/**
 * testserver.php
 * 
 * Verifies webserver environment is correctly configured
 */

class TestServer
{
	var $failures=0;
	var $warnings=0;
	
	/**
	 * Report test success
	 * @access protected
	 */
	function success($str)
	{
		echo "<li class=\"success\">".htmlentities($str)."</li>\n";
	}
	
	/**
	 * Report test failure
	 * @access protected
	 */
	function fail($str)
	{
		echo "<li class=\"fail\">".htmlentities($str)."</li>\n";
		$this->failures++;
	}
	
	/**
	 * Report test warning
	 * @access protected
	 */
	function warn($str)
	{
		echo "<li class=\"warn\">".htmlentities($str)."</li>\n";
		$this->warnings++;
	}
	
	
	/**
	 * Check the rewrite rules are working
	 * @access protected
	 * 
	 */
	function checkApache()
	{
		$url="http://{$_SERVER['HTTP_HOST']}/list";
		$ok=false;
		
		//should request it ourselves really...
		$html=@file_get_contents($url);
		if (!empty($html))
		{
			$ok=true;
			$this->success("Apache rewrite rules OK");
		}
		else
		{
			$this->fail("Request for $url failed - conf/httpd.conf web/.htaccess and check mod_rewrite is enabled");
		}
		
		return $ok;
	}
	
	/**
	 * Report test warning
	 * @access protected
	 */
	function checkPostgresConfig()
	{
		$ok=false;
		$vars = array(
			'host' => 'HOST', 
			'port' => 'PORT', 
			'dbname' => 'NAME', 
			'user' => 'USER', 
			'password' => 'PASS');
		$prefix = OPTION_PHP_MAINDB;
		
		//build connections string
	    $connstr = '';
	    foreach ($vars as $k => $v) 
	    {
	        $const="OPTION_${prefix}_DB_$v";
	        if (defined($const))
	        {
	            $connstr .= " $k='" .  constant($const) . "'";
	        }
	        else
	        {
	        	$this->fail("$const not defined in configuration file");
	        }
	    }
	    $connstr .= " connect_timeout=10 sslmode=allow";

		//attempt connection
    	$db_h = pg_connect($connstr);
		if ($db_h)
		{
			pg_close($db_h);
		
			$this->success("Postgres configured OK");
			$ok=true;
		}
		else
		{
			$err=pg_last_error();
			$this->fail("Postgres connection failed - check configuration ($err)");
		}
		
		return $ok;
	}
	
	/**
	 * Report test warning
	 * @access protected
	 */
	function checkConfigFile()
	{
		$configfile='../conf/general';
		$ok=false;
		
		$fp=@fopen($configfile,'r',true);
		if ($fp)
		{
			//include file exists!
			fclose($fp);
			
			require_once($configfile);
			$ok=true;
			$this->success("Software configuration file OK");
		}
		else
		{
			//damn, no include...
			$this->fail("$configfile not found - use general.example as basis for new config");
			
		}
		
		
		
		return $ok;
	}
	
	/**
	 * Report test warning
	 * @access public
	 */
	function execute()
	{
		$this->checkConfigFile();
		$this->checkPostgresConfig();
		
		$this->checkApache();
		
		
	}
	
}



?>
<html>
<head>
<title>Journa-listed server test</title>
<style type="text/css">
body
{
	font-family:Arial;
	
}

h1
{
	background:red;
	color:white;
	padding: 10px;
	font-weight:normal;
	
}

li.success
{
	color:#008800;
}
li.fail
{
	color:#990000;
	font-weight:bold;
}
li.warn
{
	color:#990000;
}
</style>
</head>
<body>

<h1>Journa-listed server test</h1>

<ul>
<?php
$test=new TestServer;
$test->execute();
?>
</ul>

</body>
</html>
