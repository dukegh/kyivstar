<?php

class Kyivstar {
	private $ch;
	private $res;
	private $phone;
	private $password;
	private $cookies;
	private $jsessionid;
	private $balance;

	function __construct() {
		$this->init();
	}

	function init() {
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
		curl_setopt($this->ch, CURLOPT_HEADER, 1);
		curl_setopt($this->ch, CURLINFO_HEADER_OUT, 1);
	}

	function getResValue($start, $end, $res = null) {
		if ($res === null) $res = $this->res;
		$pos1 = strpos($res, $start);
		if ($pos1 === false) return null;
		$pos2 = strpos($res, $end, $pos1 + strlen($start));
		$offset = $pos1 + strlen($start);
		return substr($res, $offset, $pos2 - $offset);
	}

	function readCookies() {
		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $this->res, $matches);
		$cookies = [];
		foreach($matches[1] as $item) {
		    parse_str($item, $cookie);
		    $cookies = array_merge($cookies, $cookie);
		}
		$this->cookies = $cookies;
	}

	function getCookie($key) {
		return isset($this->cookies[$key]) ? $this->cookies[$key] : null;
	}

	function getCh() {
		return $this->ch;
	}

	function sendGet($url) {
		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_POST, 0);
		$this->res = curl_exec($this->ch);
		$this->readCookies();
	}

	function sendPost($url, $params) {
		curl_setopt($this->ch, CURLOPT_URL, $url);
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
		$this->res = curl_exec($this->ch);
		$this->readCookies();
	}

	function setHeader($header) {
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
	}

	function getLocation() {
		return $this->getResValue('Location: ', "\r");
	}

	function getBalance() {
		return $this->balance;
	}

	function readBalance() {
		$pageData = $this->getResValue('pageData = {', '};');
		$balance = $this->getResValue('balance":"', '"', $pageData);
		$this->balance = floatval(preg_replace("/,/", ".", $balance));
	}

	function login($phone, $password) {
		$this->phone = $phone;
		$this->password = $password;
		$this->sendGet("https://account.kyivstar.ua/cas/login");
		$JSESSIONID = $this->getCookie('JSESSIONID');
		$lt_value = $this->getResValue("<input type=\"hidden\" name=\"lt\" value=\"", "\" />");
		$execution = $this->getResValue("<input type=\"hidden\" name=\"execution\" value=\"", "\"");
		echo "JSESSIONID: $JSESSIONID\n";
		echo "LT: ".$lt_value."\n";

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID;" ]);
		$this->sendGet("https://account.kyivstar.ua/cas/auth/auth.nocache.js;jsessionid=$JSESSIONID");
		$bc_value = $this->getResValue("',bc='", "'");
		echo "bc: $bc_value\n";

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID;" ]);
		$this->sendGet("https://account.kyivstar.ua/cas/auth/$bc_value.cache.js");
		$hash_value = $this->getResValue("'authSupport.rpc','", "'");
		echo "hash: $hash_value\n";

		$this->setHeader([
		    "Cookie: JSESSIONID=$JSESSIONID;",
		    'Content-Type: text/x-gwt-rpc; charset=utf-8'
		    ]);
		$params = "7|0|9|https://account.kyivstar.ua/cas/auth/|$hash_value|ua.kyivstar.cas.shared.rpc.AuthSupportRPCService|authenticate|java.lang.String/2004016611|Z|$phone|$password|https://account.kyivstar.ua/cas/login#password:|1|2|3|4|5|5|5|5|6|5|7|8|0|0|9|";
		$this->sendPost("https://account.kyivstar.ua/cas/auth/authSupport.rpc", $params);
		$token = $this->getResValue('","', '"');
		echo "token: $token\n";

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID;" ]);
		$params = "execution=$execution&lt=$lt_value&_eventId=submit&password=$password&username=$phone&rememberMe=true&token=$token&authenticationType=MSISDN_PASSWORD";
		$this->sendPost("https://account.kyivstar.ua/cas/login;jsessionid=$JSESSIONID", $params);
		$CASTGC = $this->getCookie('CASTGC');
		echo "CASTGC: $CASTGC\n";
		$title = trim($this->getResValue("<h1>", "</h1"));
		echo "$title\n";

		$this->sendGet("https://my.kyivstar.ua/tbmb/");
		$JSESSIONID2 = $this->getCookie('JSESSIONID');
		echo "JSESSIONID2: $JSESSIONID2\n";
		$location = $this->getLocation();
		echo "location: $location\n";
		// http://my.kyivstar.ua/tbmb/login/show.do
		$location = preg_replace('/^http:/', 'https:', $location) . ";jsessionid=$JSESSIONID2";

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID2;" ]);
		$this->sendGet($location);
		$location = $this->getLocation();
		echo "location: $location\n";
		// http://account.kyivstar.ua/cas/login?service=http%3A%2F%2Fmy.kyivstar.ua%3A80%2Ftbmb%2Fdisclaimer%2Fshow.do&locale=ua

		$this->sendGet($location);
		$JSESSIONID3 = $this->getCookie('JSESSIONID');
		echo "JSESSIONID3: $JSESSIONID3\n";
		$locale = $this->getCookie('org_springframework_web_servlet_i18n_CookieLocaleResolver_LOCALE');
		echo "LOCALE: $locale\n";
		$location = $this->getLocation();
		echo "location: $location\n";
		// https://account.kyivstar.ua/cas/login?service=http%3A%2F%2Fmy.kyivstar.ua%3A80%2Ftbmb%2Fdisclaimer%2Fshow.do&locale=ua

		$this->sendGet("https://new.kyivstar.ua/ecare/");
		$this->jsessionid = $JSESSIONID4 = $this->getCookie('JSESSIONID');
		echo "JSESSIONID4: $JSESSIONID4\n";
		$location = $this->getLocation();
		echo "location: $location\n";
		// http://account.kyivstar.ua/cas/login?service=http%3A%2F%2Fnew.kyivstar.ua%2Fecare%2F
		$location = preg_replace('/^http:/', 'https:', $location) . ";jsessionid=$JSESSIONID4";

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID3; org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE=$locale; CASTGC=$CASTGC;" ]);
		$this->sendGet($location);
		$location = $this->getLocation();
		echo "location: $location\n";
		// http://new.kyivstar.ua/ecare/;jsessionid=7BEB8534FF3850D6BB9E9C654CDAD320.dgt03?ticket=ST-269236-GckIWegE2AdBHKsFuMIm-s2n1
		$location = preg_replace('/^http:/', 'https:', $location);

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID4;" ]);
		$this->sendGet($location);
		$location = $this->getLocation();
		echo "location: $location\n";
		// http://new.kyivstar.ua/ecare/;jsessionid=7BEB8534FF3850D6BB9E9C654CDAD320.dgt03
		$location = preg_replace('/^http:/', 'https:', $location);

		$this->setHeader([ "Cookie: JSESSIONID=$JSESSIONID4;" ]);
		$this->sendGet($location);
		$this->readBalance();
	}
}
