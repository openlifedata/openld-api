<?php
$error  = '';

$lserver = "http://localhost";
$fserver = "http://localhost:8890/sparql"; // federate
$server_list = "servers.tab";
$request = parseRequest();
if(isset($request['args']['example'])) {
	$url = $request['scheme'].'://'.$request['host'].substr($request['path'],0, strrpos($request['path'],'/')+1).$request['args']['example'];
	header("Location: $url",TRUE,303);
	exit;
}
//$path = substr($request['path'],0, strrpos($request['path'],'/')+1);
$out = '';
// services
switch($request['path'])
{
	case "/":
		//api_info();
		break;
	case "/label/":
		$out = label($request);
		break;
	case "/search/":
		$out = search($request);
		break;
	case "/describe/":
		$out = describe($request);
		break;
	case "/sparql/":
		$out = sparql($request);
		break;
	case "/inlinks/":
		$out = inlinks($request);
		break;
	case "/outlinks/":
		$out = outlinks($request);
		break;
	case "/instances/":
		$out = getInstances($request);
		break;
	default:
		//showDefault($request);
		$msg = "oops! I don't understand ".$request['path'];
		exit;
}

$etag = hash('sha256',$out,false);
if(isset($args['action'])) {
	# download
	header("Content-Type: $accept_format");
	header("Content-Length: ".strlen($content));
	header("Content-Disposition: attachment; filename=\"sparql.srx\"");
	header("ETag: \"$etag\"");
}
// rewrite
$out = str_replace('bio2rdf.org','openlifedata.org',$out);

echo $out;
exit;


function showDefault($request)
{
	echo '<p>Search: <a href="/api/search/?q=lepirudin">search</a>';
	echo '<p>search w/filter: <a href="/api/search/?q=molecular%20function&filter=dataset:http://bio2rdf.org/bioportal_resource:bio2rdf.dataset.bioportal.R3">search</a>';
	echo '<p>Describe: <a href="/api/describe/?q=http://bio2rdf.org/drugbank:DB00001">describe</a></p>';
    echo '<p>Out Links: <a href="/api/outlinks/?q=http://bio2rdf.org/drugbank:DB00001">out links</a></p>';
    echo '<p>In Links: <a href="/api/inlinks/?q=http://bio2rdf.org/drugbank:DB00001">in links</a></p>';
   	echo '<p>Instances: <a href="/api/instances/?q=http://bio2rdf.org/drugbank_vocabulary:Drug">instances</a></p>';
	
}



function getPrefixes()
{
	$prefixes['void'] = "http://rdfs.org/ns/void#";
	$prefixes['dct']  = "http://purl.org/dc/terms/";

	$p = '';foreach($prefixes AS $k => $v) {$p .= "PREFIX $k: <$v>".PHP_EOL;}
	return $p;
}

function getContentTypes()
{
 $content_types = array(
	 "construct_describe" => array(
		"html+microdata" => "text/html",
		"ttl" => "text/turtle",
		"nt" => "text/plain",
		"trig" => "application/x-trig",
		"json-ld" => "application/x-json+ld",
		"json-udata" => "application/microdata+json",
		"json-odata" => "application/odata+json",
		"rdf-json" => "application/rdf+json",
		"rdf-xml" => "application/rdf+xml",
		"atom" => "application/atom+xml",
		"csv" => "text/csv",
		"tsv" => "text/tab-separated-values",
		"rdfa" => "application/xhtml+xml",
	 ),
	 "select_ask" => array(
		"html" => "text/html",
		"json" => "application/sparql-results+json",
		"xml"  => "application/sparql-results+xml",
		"ttl" => "text/turtle",
		"rdf+xml" => "application/rdf+xml",
		"nt"  => "text/plain",
		"csv" => "text/csv",
		"tsv" => "text/tab-separated-values"
	 )
 );
 return $content_types;
}

function getBio2RDFURI($uri)
{
	if(FALSE == strstr($uri,"http")) {
		$uri = "http://openlifedata.org/".$uri;
	}
	$patterns[] = 'http://purl.obolibrary.org/obo/([A-Z]+)_([0-9]+)';
	$patterns[] = 'http://openlifedata.org/([^:]+):(.*)$';
	foreach($patterns AS $pattern) {
		$new_uri = preg_replace_callback(
        "|$pattern|",
        function ($matches) {
            return "http://bio2rdf.org/".strtolower($matches[1]).":".$matches[2];
        },
        $uri
		);
	}
	return $new_uri;
}


function describe($args)
{
	$uri = "<".$args['args']['q'].">";
	$construct = "?s ?p ?o";
	$where = "?s ?p ?o FILTER(?s = $uri)";
	return runQuery($args, $construct, $where);
}

function label($args)
{
	$v = '';
	foreach(explode(" ",$args['args']['q']) AS $uri) {$v .= "<$uri> ";} $values = " VALUES ?uri { $v } ";
	$construct = "?s dct:title ?title; dct:description ?description; rdf:type ?type; void:inDataset ?dataset .";
	$where = "$values ?s dct:title ?title; rdf:type ?type; void:inDataset ?dataset . OPTIONAL{?s dct:description ?description.} FILTER ( ?s = ?uri )";
	return runQuery($args, $construct, $where);
}

function outlinks($args)
{
	$uri = "<".$args['args']['q'].">";
	$limit = " LIMIT 10 "; $offset = '';
	if(isset($args['limit'])) $limit = " LIMIT ".$args['limit'];
	if(isset($args['offset'])) $offset = " OFFSET ".$args['offset'];

	$construct = "?s dct:title ?title; dct:description ?description; rdf:type ?type; void:inDataset ?dataset; ?p ?uri .";
	$where = "SELECT distinct * { ?s rdf:type ?type; void:inDataset ?dataset; dct:title ?title; ?p $uri. OPTIONAL{?s dct:description ?description.} } $limit $offset";
	return runQuery($args, $construct, $where);
}

function inlinks($args)
{
	$uri = "<".$args['args']['q'].">";
	$limit = " LIMIT 10 "; $offset = '';
	if(isset($args['limit'])) $limit = " LIMIT ".$args['limit'];
	if(isset($args['offset'])) $offset = " OFFSET ".$args['offset'];

	$construct = "?uri ?p ?o. ?o dct:title ?title; dct:description ?description; rdf:type ?type; void:inDataset ?dataset.";
	$where = "SELECT * { $uri ?p ?o. ?o rdf:type ?type; void:inDataset ?dataset. OPTIONAL{?o dct:title ?title.} OPTIONAL{?o dct:description ?description.} FILTER (!isLiteral(?o)) } $limit $offset ";
	return runQuery($args, $construct, $where);
}

function getInstances($args)
{
	$uri = " <".$args['args']['q']."> ";
	$limit = " LIMIT 10 "; $offset = '';
	if(isset($args['limit'])) $limit = " LIMIT ".$args['limit'];
	if(isset($args['offset'])) $offset = " OFFSET ".$args['offset'];
	
	$construct = "?s a ?type.";
	$where = "SELECT * {?s a ?type . FILTER(?type = $uri )} $limit $offset";
	return runQuery($args, $construct, $where);
}

function search($args)
{
	$search_term = $args['args']['q'];
	$url = "http://aber-owl.net:17000/QueryBio2RDF.groovy?query=".$search_term;
	return file_get_contents($url);
	
	$search_term = $args['args']['q'];
	if(strlen($search_term)  < 3) {
		echo $error = "Search term must be greater than 2 characters";
		return FALSE;
	}

	$filter = '';
	if(isset($args['args']['filter'])) {
		foreach(explode(",",$args['args']['filter']) AS $kv) {
			$a = explode(":",$kv,2);
			if($a[0] == "dataset.name")  $filter .= ' FILTER REGEX (str(?s),"/'.$a[1].'(_vocabulary|_resource)?:", "i") ';
			elseif($a[0] == "dataset.uri") $filter .= ' FILTER (?dataset = <'.trim($a[1]).'>) ';
			elseif($a[0] == "type") $filter .= ' FILTER (?type = <'.trim($a[1]).'>) ';
	}}
	if(isset($args['args']['qualifier']) and $args['args']['qualifier'] == 'exact') {
		$search = ' FILTER REGEX(str(?title), "^'.$search_term.'$", "i") ';
		//$search = ' FILTER(STRSTARTS(STR(?title), "'.$search_term.'"))';
		//$search = ' FILTER (?title = "'.$search_term.'"@en)';
	} else {
		$search = ' ?title bif:contains "\''.$search_term.'\'" .';
	}

	$limit = " LIMIT 10 "; $offset = '';
	if(isset($args['limit'])) $limit = " LIMIT ".$args['limit'];
	if(isset($args['offset'])) $offset = " OFFSET ".$args['offset'];
	
	$construct = "?s dct:title ?title; dct:description ?description; rdf:type ?type; void:inDataset ?dataset.";
	$where = "SELECT * {?s dct:title ?title; rdf:type ?type; void:inDataset ?dataset. OPTIONAL{?s dct:description ?description.} $search $filter } $limit $offset";

	return runQuery($args, $construct, $where);
}


function runQuery($args, $construct, $where)
{
	global $fserver;
	$q = '';
	foreach(getServers() AS $server) {
		$q .= "{SERVICE <$server> { ".$where." }} UNION ";
	}
	$q = substr($q,0,-6);
	$query = getPrefixes()."CONSTRUCT { ".$construct." } WHERE { $q }";

	$a['query'] = $query;
	$a['format'] = $args['format'];
	$output = postQuery($fserver, $a, $code);
	if($code != 200) {
		header("HTTP /1.1 $code");
		echo $output;
		exit;
	}

	return $output;
}

function parseRequest($valid_fields = null)
{
	if(!isset($_SERVER['SERVER_NAME'])) {
		return null;
	}
	$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	$purl = parse_url($url);
	$path = $purl['path'];

	if(isset($purl['query'])) {
		$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
		if($request_method == "GET") {
			parse_str($purl['query'],$myargs);
		} else if($request_method == "POST") {
			$myargs = $_POST;
		}
		$args = null;
		foreach($myargs AS $k => $v) {
			if(isset($valid_fields)) {
				if(isset($valid_fields[$k]) and isset($myargs[$k])) $args[$k] = $myargs[$k];
			} else {
				$args[$k] = $myargs[$k];
			}
		}
		$purl['args'] = $args;
	}
	if(isset($purl['args']['q']) and $purl['args']['q']) {
		$purl['search'] = getBio2RDFURI($purl['args']['q']);
		$purl['args']['q'] = getBio2RDFURI($purl['args']['q']);
	}

	if(isset($args['limit'])) $purl['limit'] = $args['limit'];
	if(isset($args['offset'])) $purl['offset'] = $args['offset'];	

	//$format = $_SERVER['HTTP_ACCEPT'];
	if(isset($args['format'])) $purl['format'] = getFormat($args['format']);
	else $purl['format'] = getFormat('json-ld');
	return $purl;
}


function postQuery($url, $fields, &$code)
{
	if(!isset($fields['query'])) {
		trigger_error("No query specified");
		return false;
	}
	if(!isset($fields['format'])) $format = "text/plain";
	else $format = $fields['format']= getFormat($fields['format']);

	$q = '';
	foreach($fields AS $k => $v) {$q .= "&".$k."=".urlencode($v);}
	$q = substr($q,1);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: $format","Content-type: application/x-www-form-urlencoded;charset=UTF-8", "Content-length: ".strlen($q)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return $output;
}

function getServers($server_file = null)
{
	global $server_list, $lserver;
	$fp = fopen($server_list,"r");
	if($fp === NULL) {
		return null;
	}
	fgets($fp);
	while($l = fgets($fp)) {
		if($l[0] == "#") continue;
		$a = explode("\t",trim($l));
		if($a[0] == '') continue;
		if($a[1] == "14003") continue;
		/*$cmd = "pgrep -xf ./b-".$a[2];
		$status = exec($cmd, $out);
		if(!$status) continue;
		*/
		//$servers[$a[2]]['isql'] = trim($a[0]);
		//$servers[$a[2]]['web'] = $a[1];
		$servers[$a[2]] = $lserver.':'.$a[1].'/sparql';
	}
	ksort($servers);
	return $servers;
}


exit;

$remote_label = "bio2rdf";
$remote_id    = "bio2rdf.org";
$target_label = "openlifedata";
$target_id    = "openlifedata.org";


if(!isset($_SERVER['SERVER_NAME'])) $proxy_id = "localhost";
else $proxy_id  = $_SERVER['SERVER_NAME'];
$proxy_server = "http://$proxy_id";

$server_list = "/media/bio2rdf/3/instances.tab";
$servers = getServers($server_list);
ksort($servers);
$error  = '';

// dataset specific page
# http://openlifedata.org/affmetrix
# http://openlifedata.org/affmetrix/

// sparql proxy
# http://openlifedata.org/affmetrix/sparql?query=X&format=XXX&view=[html]&apikey=XXX&urischeme=XXX

// linked data
# http://openlifedata.org/prefix:id[&format=X&view=[true]&describe=[s|so]]
# http://openlifedata.org/prefix:id.[nt,ttl,xml,json-ld,json]
# http://openlifedata.org/uri?http://bio2rdf.org/drugbank_vocabulary:Drug

// virtuoso redirect
# http://openlifedata.org/affmetrix/v-sparql/
# http://openlifedata.org/affmetrix/v-browser/

$content_types = array(
 "construct_describe" => array(
	"html+microdata" => "text/html",
//	"turtle-pretty" => "application/x-nice-turtle",
	"ttl" => "text/turtle",
	"nt" => "text/plain",
	"trig" => "application/x-trig",
	"json-ld" => "application/x-json+ld",
	"json-udata" => "application/microdata+json",
	"json-odata" => "application/odata+json",
	"rdf-json" => "application/rdf+json",
	"rdf-xml" => "application/rdf+xml",
	"atom" => "application/atom+xml",
	"csv" => "text/csv",
	"tsv" => "text/tab-separated-values",
	"rdfa" => "application/xhtml+xml",
//	"html-list" => "text/x-html+ul",
//	"html-table" => "text/x-html+tr",
//	"html+microdata-pretty" => "application/x-nice-microdata",
//	"spreadsheet" => "application/vnd.ms-excel",
	"facet" => "x-application/facet-browser",
	"*/*" => "application/sparql-results+xml"
 ),
 "select_ask" => array(
	"html" => "text/html",
	"json" => "application/sparql-results+json",
	"xml"  => "application/sparql-results+xml",
	"ttl" => "text/turtle",
	"rdf+xml" => "application/rdf+xml",
	"nt"  => "text/plain",
//	"js" => "application/javascript",
	"csv" => "text/csv",
	"tsv" => "text/tab-separated-values"
 )
);
if(!isset($_SERVER['REQUEST_URI'])) {
  // must be a cmd line request
  exit;
}

$uri = substr($_SERVER['REQUEST_URI'],1);
$accept_format = getFormat($_SERVER['HTTP_ACCEPT']);
$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
$etag_request = isset($_SERVER['HTTP_IF_NONE_MATCH'])?$_SERVER['HTTP_IF_NONE_MATCH']:'';

// break the uri
$a = explode("/",$uri,2);
// dataset-specific endpoint services
if(isset($a[0]) and $a[0] == "network") {
//	header("Location: $uri",TRUE,303);
}


if(isset($a[1])
	and preg_match("/^(sparql|v-sparql|v-browser|v-manager|describe|api)/",$a[1])) {

	$prefix = strtolower($a[0]);
	if(!isset($servers[$prefix])) {
		echo "Unrecognized endpoint $prefix";
		exit;
	}
	$lserver = $local_server.":".$servers[$prefix]['web'];
	$pserver = $proxy_server.":".$servers[$prefix]['web'];
	$rserver = $pserver;

	if(preg_match("/^sparql/",$a[1])) {
		$valid_fields = array("query","apikey","format","action","urischeme");
		$msg = null;
		if($request_method == "GET") {
			$q = substr($a[1],strlen("sparql")+1);
			parse_str($q,$args);
			$uri = "/".substr($uri,0,strpos($uri,'?'));
		} else if($request_method == "POST") {
			foreach($valid_fields AS $f) {
				if(isset($_POST[$f])) $args[ $f ] = $_POST[$f];
			}
		}

		if(!isset($args['query']) or $args['query'] == '') {
			$msg = "You must provide a SPARQL query using the parameter 'query' e.g. query=XXX";
		} else if(!isset($args['apikey']) or $args['apikey'] == '') {
#			$msg = "You must provide a valid API key using the parameter 'apikey'  e.g. apikey=XXXX";
		}
		if(isset($args['urischeme'])) {
			if(!in_array($args['urischeme'], array("default","original"))) {
				$msg = "Invalid URI scheme: Choose from default or original";
			}
		} else $args['urischeme'] = 'default';

		if($msg) {
			header("HTTP /1.1 400 Bad Request");
			echo "<error>".$msg."</error>";
			exit;
		}
		if(isset($args['format'])) {
			$args['format'] = getFormat($args['format']);
		} else $args['format'] = getFormat($accept_format);
		if(!isset($args['action'])) $args['action'] = 'download';

		// rewrite the query from openlifedata uris to bio2rdf, and make sure we use the right proxy server
		$query = $args['query'];
		$query = str_replace($proxy_id,$target_id,$query);
		$query = str_replace($target_id,$remote_id,$query);
		$query = preg_replace("/http:\/\/bio2rdf.org\/([^\/]+)\/sparql/",$proxy_server."/$1/sparql",$query);
		$args['query'] = $query;

		// make the call
		$output = postQuery($lserver."/sparql", $args, $code);
		if($code != 200) {
			header("HTTP /1.1 $code");
			echo $output;
			exit;
		}

		$etag = hash('sha256',$output,false);
		if($etag_request != '' and str_replace('"','',$etag_request) == $etag) {
			header("HTTP /1.1 304 Not Modified");
			exit;
		}
		header("HTTP /1.1 200 OK");
		header("Allow: GET,POST");

		if(stristr($args['query'],"DESCRIBE")) $query_type='construct_describe';
		else if(stristr($args['query'],"CONSTRUCT")) $query_type='construct_describe';
		else $query_type='select_ask';
		$accept_list = implode(',', array_unique(array_values($content_types[$query_type])));
		header("Accept-Post: ".$accept_list);

		// rewrite the content if requested
		if($args['urischeme'] == 'original') {
			$content = $output;
		} else {
			$content = str_replace($remote_label,$target_label,$output);
			$content = str_replace($remote_id,$target_id,$output);
		}

		if($args['action'] == 'view') {
			renderHTML($uri, $content, $args, $query_type);
		} else {
			# download
			header("Content-Type: $accept_format");
			header("Content-Length: ".strlen($content));
			header("Content-Disposition: attachment; filename=\"sparql.srx\"");
			header("ETag: \"$etag\"");
			echo $content;
			exit;
		}

	} else if(preg_match("/^v-sparql/",$a[1])) {
		$url = $rserver."/sparql";
	} else if(preg_match("/^v-browser/",$a[1])) {
		$url = $rserver."/fct";
	} else if(preg_match("/^v-manager/",$a[1])) {
		$url = $rserver."/";
	} else if(preg_match("/^network/",$a[1])) {
		$url = $rserver."/";
	} else if(preg_match("/^describe/",$a[1])) {
		$url = $rserver."/".str_replace("describe","describe/",$a[1]);
	} else {
		// only the prefix i specified
		$url = $pserver."/fct/";
	}
	header("Location: $url",TRUE,303);
	exit;
}

if($a[0] != '') {
	$str = implode("/",$a);
	// the dataset is specified either  as /dataset or /prefix:id or /prefix:id&args=arg
	if(isset($servers[$str]) ) {
		// show the info page
		$url = "http://download.bio2rdf.org/current/$str/$str.html";
		header("Location: $url",TRUE, 303);
		exit;
	}

	// handle the reference uri
	if(preg_match("/^\?uri=(.*)/",$str,$m)) {
		$uri = $m[1];
	} else {
		$uri = "http://".$proxy_id.$purl['path'];
	}
	$args = '';
	if(isset($purl['query'])) parse_str($purl['query'], $args);
	if(!isset($args['describe'])) $args['describe'] = 's';
	if(!isset($args['urischeme'])) $args['urischeme'] = 'default';
	if(!isset($args['format'])) {
		if($accept_format == "text/html") {
			$args['format'] = "html+microdata";
		} else if ($accept_format != '') {
			$args['format'] = $accept_format;
		} else {
			$args['format'] = 'text/turtle';
		}
	} 
	if(!isset($args['action'])) {
		if($accept_format == "text/html") $args['action'] = 'html';
		else $args['action'] = '';
	}
	if(isset($args['format']) and getFormat($args['format']) == "x-application/facet-browser") {
		$fct_url = '';

		// determine server to send to
		if(preg_match("/([^:]+):(.*)/",$purl['path'],$m)) {
			$dataset = $ns = substr($m[1],1);
			$id = $m[2];
			$u = "http://$remote_id".$purl['path'];

			if(FALSE !== ($pos = strrpos($ns,"_resource"))) {$dataset = substr($ns,0,$pos);}
			if(FALSE !== ($pos = strrpos($ns,"_vocabulary"))) {$dataset = substr($ns,0,$pos);}

			if(isset($servers[$dataset])) {
				$fserver = $proxy_server.":".$servers[$dataset]['web'];
				$fct_url = $fserver."/describe/?url=".urlencode($u);
			}
		}

		if($fct_url) {
			header("Location: $url",TRUE,303);
			exit;
		}
	}

	$u = str_replace(array($proxy_id,$target_id),$remote_id, $uri);
	$fserver = "http://localhost:14050"; // federate

# $q .= "{SERVICE <$e> {?s ?p ?o FILTER(?s=<$u>) OPTIONAL{?o rdfs:label ?label}}} UNION ";
# $q .= "{SERVICE <$e> {{?s ?p ?o FILTER(?s=<$u>) OPTIONAL{?o rdfs:label ?label}}UNION{?s ?p ?o FILTER(?o=<$u>)}}} UNION ";
# $q = "query=".urlencode("CONSTRUCT {?s ?p ?o. ?o rdfs:label ?label} OPTION (get:soft \"soft\", get:method \"GET\") { $q }");

	$q = '';
	foreach($servers AS $server) {
		$e = $local_server.":".$server['web']."/sparql";
		if($args['describe'] == "so") $q .= "{SERVICE <$e> {{?s ?p ?o FILTER(?s=<$u>)}UNION{?s ?p ?o FILTER(?o=<$u>)}}} UNION ";
		else if($args['describe'] == "s") $q .= "{SERVICE <$e> {?s ?p ?o FILTER(?s=<$u>)}} UNION ";
		else if($args['describe'] == "o") $q .= "{SERVICE <$e> {?s ?p ?o FILTER(?o=<$u>)}} UNION ";
		else if($args['describe'] == "p") $q .= "{SERVICE <$e> {?s ?p ?o FILTER(?p=<$u>)}} UNION ";
	}
	$q = substr($q,0,-6);


	$url = $fserver."/sparql";
	$query = "CONSTRUCT {?s ?p ?o. ?o rdfs:label ?label} WHERE { $q }";
	$args['query'] = $query;
	// run the query
	$output = postQuery($url, $args, $code);

	if($args['urischeme'] == 'default') {
		$content = str_replace($remote_label,$target_label,$output);
		$content = str_replace($remote_id,$target_id,$content);
	} else $content = $output;

	$etag = hash('sha256',$content,false);

	$format = getFormatLabel(getFormat($args['format']));
	if($args['action'] == 'download') {
		# download
		header("Content-Type: $accept_format");
		header("Content-Length: ".strlen($content));
		header("Content-Disposition: attachment; filename=\"sparql.srx\"");
		header("ETag: \"$etag\"");
		echo $content;
		exit;
	} else if($args['action'] == 'html') {
		$myargs = $args;
		unset($myargs['query']);
		renderHTML($uri, $content, $myargs, 'construct_describe');
	} else {
		echo $content;
	}

	exit;
}

function parseQuery($query)
{
	$list = explode("&",$query);
	foreach($list AS $item) {
		@list($k,$v) = explode("=",$item);
		if(!$k) continue;
		$data[$k] = $v;
		if($k =="format" and $v != '') {
			$format = getFormat($v);
			if($format === null) {
				$format = getFormat($_SERVER['HTTP_ACCEPT']);
			}
			$data[$k] = $format;
		}
	}

	return $data;
}

function getFormatLabel($str)
{
	global $content_types;
	$m = array("select_ask","construct_describe");
	foreach($m AS $n) {
		$a = array_flip($content_types[$n]);
		if(isset($a[$str])) return $a[$str];
	}
	return $str;
}

function getFormat($str)
{
	$content_types = getContentTypes();

	if($str == "rdf xml") $str = "rdf+xml";
	$list = explode(",",$str);
	foreach($list AS $l) {
		$s = explode(";",$l);
		$ct = $s[0];
		if(isset($s[1])) $pref = $s[1];
		// search the select/ask array
		$m = array("select_ask","construct_describe");
		foreach($m AS $n) {
			// check the key
			if(isset($content_types[$n][$ct])) {
				return $content_types[$n][$ct];
			}
			// check the value
			if(FALSE !== ($k = array_search($ct,$content_types[$n]))) {
				return $content_types[$n][$k];
			}
		}
	}
	return null;
}




function showDatasets()
{
	global $servers, $server, $local_server, $proxy_server;
	$i = 1;
	$b  = '<strong>Datasets</strong>';
	$b .= '<table>';
	foreach($servers AS $name => $o) {
		$b .= '<tr>';
		$b .= '<td>'.($i++).'</td>';
		$myserver = $proxy_server.":".$o['web'];
		$title = $name;

		$endpoint = $myserver.'/sparql';
		$b .= '<td>[<a target="_blank" href="'."http://yasgui.laurensrietveld.nl/?contentTypeConstruct=text%2Fturtle&contentTypeSelect=application%2Fsparql-results%2Bxml&endpoint=$endpoint/&outputFormat=table&query=PREFIX+rdf%3A+%3Chttp%3A%2F%2Fwww.w3.org%2F1999%2F02%2F22-rdf-syntax-ns%23%3E%0APREFIX+rdfs%3A+%3Chttp%3A%2F%2Fwww.w3.org%2F2000%2F01%2Frdf-schema%23%3E%0APREFIX+void%3A+%3Chttp%3A%2F%2Frdfs.org%2Fns%2Fvoid%23%3E%0APREFIX+d%3A+%3Chttp%3A%2F%2Fbio2rdf.org%2Fbio2rdf.dataset_vocabulary%3A%3E%0ASELECT+*%0AWHERE+%0A%7B%0A++%5B%5D+void%3Asubset+%5B%0A+++++a+d%3ADataset-Type-Count+%3B%0A+++++void%3Aclass+%3Fclass%3B%0A+++++void%3Aentities+%3Fentities%3B%0A+++++void%3AdistinctEntities+%3FdistinctEntities%0A+++++%5D+.%0A%7D+ORDER+BY+DESC(%3Fentities)%0A++++%0A%0A&requestMethod=POST&tabTitle=$title".'">yasgui</a>]</td>';
		$b .= '<td>[<a target="_blank" href="'.$myserver.'/">manage</a>]</td>';
		$b .= '<td>[<a target="_blank" href="'.$myserver.'/fct/">search</a>]</td>';
		$b .= '<td>[<a target="_blank" href="'.$myserver.'/sparql/">sparql-ui</a>]</td>';
		$b .= '<td>[<a target="_blank" href="'.$proxy_server."/".$name.'/sparql">sparql</a>]</td>';
		$b .= '<td>'.$name.'</td>';
		$b .= '</tr>';
	}
	$b.= '</table>';
	return $b;
}

function getParams($args) 
{
	$q = '';
	foreach($args AS $k => $v) {
		$q .= "&".$k."=".urlencode($v);
	}
	$q = substr($q,1);
	return $q;
}

function getViews($uri, $args, $type = "construct_describe", $format = null)
{
	global $content_types, $proxy_server;
	$b = '[<a href="'.$proxy_server.'">'.$proxy_server.'</a>] ';
	unset($args['format']);
	$q = getParams($args);

	foreach($content_types[$type] AS $k => $v) {
		if($k == '*'.'/*') continue;
		if(!isset($format) or $format == $k) $b .= '[<a href="'.$uri.'?'.$q.'&format='.urlencode($k).'">'.$k.'</a>]';
	}
	$b .= '<br>';
	return $b;
}

function renderHTML($uri, $c, $args, $query_type = 'construct_describe')
{
	global $target_id,$proxy_id;

	$format = getFormatLabel($args['format']);
	echo getViews($uri, $args, $query_type).'<br>';
	$myargs = $args; unset($myargs['query']);
	$params = "?".getParams($myargs);

	if(preg_match("/(html|nice-microdata)/",$format)) {
		$c = str_replace("http://localhost:8890/describe/",$dataset."/describe",$c);
		$c = str_replace($target_id,$proxy_id,$c);
		$c = preg_replace("/href=\"http:([^\"]+)\">/","href=\"http:\${1}$params\">",$c);
		echo $c;exit;
	} else if(preg_match("/(ttl|nt|trig)/",$format))  {
		// get the namespaces
		preg_match_all("/@prefix ([a-z0-9]+):	<(.*)>/",$c,$m);
		if(isset($m[1])) {
			foreach($m[1] AS $k => $prefix) {
				$base_uri = $m[2][$k];
				$c = preg_replace("/$prefix:([^ |	]+)/","<a href=\"".$base_uri."\${1}\">$prefix:\${1}</a>",$c);
			}
		}
		$c = preg_replace("/<http:\/\/([^>]+)>/","<a href=\"http://\${1}\">http://\${1}</a>",$c);
		if($format == "ttl") $c = preg_replace("/http:\/\/$target_id\/([^\"]+)/","http://$proxy_id/\${1}$params",$c);
		if($format == "nt" or $format == "trig") $c = preg_replace("/\"http:\/\/$target_id\/([^\"]+)/","\"http://$proxy_id/\${1}$params",$c);
	} else if(preg_match("/(json|csv|tsv|tab)/",$format)) {
		$c = preg_replace("/\"http:\/\/([^\"]+)\"/","\"<a href=\"http://\${1}\">http://\${1}</a>\"",$c);
		$c = preg_replace("/href=\"http:\/\/$target_id\/([^\"]+)/","href=\"http://$proxy_id/\${1}$params",$c);
	} else if(preg_match("/xml/",$format)) {
		$c = htmlspecialchars(str_replace($remote_id,$proxy_id,$c));
		preg_match_all("/xmlns:([a-z0-9]+)=&quot;([^&]+)&quot;/",$c,$m);
		if(isset($m[1])) {
			foreach($m[1] AS $k => $prefix) {
				$base_uri = $m[2][$k];
				$c = preg_replace("/&lt;[\/]?$prefix:([^ ]+)/","&lt;<a href=\"".$base_uri."\${1}$params\">$prefix:\${1}</a>",$c);
			}
		}
		$c = preg_replace("/&quot;http:\/\/$target_id\/([^&]+)&quot;/","&quot;<a href=\"http://$proxy_id/\${1}$params\">http://$target_id/\${1}</a>&quot;",$c);
		$c = preg_replace("/&quot;http:\/\/([^&]+)&quot;/","&quot;<a href=\"http://\${1}\">http://\${1}</a>&quot;",$c);
		echo nl2br($c);
		exit;
	} else {
		echo nl2br(htmlspecialchars(str_replace($remote_id,$proxy_id,$c)));
		exit;
	}
	echo nl2br($c);
	exit;

}

?>
<h1>OpenLifeData.org</h1>
<p>Openlifedata is a new initiative by the <a href="http://dumontierlab.com">Dumontier Lab</a> to facilitate access to semantically represented biomedical data.</p>

<h2>API</h2>
<p>You can use GET or POST with any service request.</p>

<h3>Entity lookup</h3>
 <p>URL: <?php echo $proxy_server;?>/<em>dataset</em>:identifier</p>
 <p>parameters:</p>
 <ul>
  <li> format (optional): specify the desired format (use formats keywords or media types listed at bottom of page)
  <li> action (optional): specify whether to view an hypertext version of the format (action=view) or download the original (action=download) 
 </ul>
 <p>Examples:</p>
 <ul>
  <li>id lookup (html+microdata): <a href="<?php echo $proxy_server;?>/drugbank:DB00363">Clozapine</a>
  <li>id lookup (n-triples): <a href="<?php echo $proxy_server;?>/drugbank:DB00363&format=n-triples&action=view">Clozapine</a>
  <li>id lookup: <?php echo getViews("$proxy_server/drugbank:DB00363");?>
 </ul>

<h3>SPARQL Query</h3>
 <p>SPARQL queries can currently be performed on dataset-specific sparql endpoints</p>
 SPARQL endpoint: http://openlifedata.org/<em>dataset</em>/sparql
 <p>This service can be accessed using GET or POST. Use the following parameters, with URL encoded values:</p>
<ul>
  <li> query (mandatory): a valid SPARQL query
  <li> apikey (mandatory): a valid API Key (request one from <a href="mailto:michel.dumontier@stanford.edu">michel dumontier</a>)
  <li> describe (optional): use the identifier in the subject position (describe=s), predicate position (describe=p), object position (describe=o), or in both subject and object position (describe=so)
  <li> format (optional): specify the desired format (use either at bottom of page)
  <li> action (optional): specify whether to view an hypertext version of the format (action=view) or download the original (action=download) 
</ul>
 <p>Examples:</p>
 <ul>
  <li>sparql describe (json-ld): <a href="<?php echo $proxy_server;?>/drugbank/sparql?query=DESCRIBE <http://openlifedata.org/drugbank:DB00363>&format=json-ld&action=view&apikey=demo">Clozapine</a>
 </ul>

<br>

Dataset info page: <?php echo $proxy_server;?>/<em>dataset</em>/
<br>
Source data browser: <?php echo $proxy_server;?>/<em>dataset</em>/v-browser/
<br>
Source SPARQL endpoint: <?php echo $proxy_server;?>/<em>dataset</em>/v-sparql/

<br>
<strong><?php echo $error;?></strong>
<br>
<?php echo showDatasets();?>
<br>
<br>
<strong>supported formats / HTTP content-type requests</strong><br>
<?php
 foreach($content_types AS $k => $v) {
   echo "<em>".str_replace("_","/",$k)."</em><br>";
   echo "<table>";
   echo "<tr><th>format</th><th>content-type</th></tr>";
   foreach($v AS $k => $v) {
     echo "<tr><td>$k</td><td>$v</td></tr>";
   }
   echo "</table>";
  echo "<br>";
 }
?>
