<?php

$tester = new Tester();

/** Uncomment a line to test an API method */

/**  API Method:  gxproduct.checkSkus  **/
// $tester->checkSkus();

/**  API Method:  gxproduct.update  **/
// $tester->update();

/**  API Method:  gxproduct.update with field mapping  **/
// $tester->updateWithFieldMap();


class Tester {

    protected $domain_name = 'example.com';
    protected $api_user = 'tester';
    protected $api_key  = 'tester';

    protected $skus_file   = 'skus.csv';
    protected $update_file = 'update.csv';
    protected $update_fieldmap_file = 'update-fieldmap.csv';

    protected $start_time, $stop_time, $qty;
    protected $soap;

    public function __construct()
    {
        $this->soap = new SoapClient('http://' . $this->domain_name . '/api/soap/?wsdl=1');
    }

    public function update()
    {
        $data = $this->getUpdateFromCsv($this->update_file);
        $client = $this->soap;

        $session = $client->login($this->api_user, $this->api_key);
        $this->startTimer();
        try {
            $response = $client->call($session, 'gxproduct.update', array($data));
            $this->printUpdateResponse($response);
        } catch (Exception $e) {
            echo "API Fault: " . $e->getMessage() . PHP_EOL;
        }
        $this->stopTimer();
        $client->endSession($session);
        
        $this->reportTime(count($data));
    }

    public function updateWithFieldMap()
    {
        $data = $this->getUpdateFromCsv($this->update_fieldmap_file);
        $client = $this->soap;

        $session = $client->login($this->api_user, $this->api_key);
        $this->startTimer();
        try {
            $response = $client->call($session, 'gxproduct.update', array($data, 'example'));
            $this->printUpdateResponse($response);
        } catch (Exception $e) {
            echo "API Fault: " . $e->getMessage() . PHP_EOL;
        }
        $this->stopTimer();
        $client->endSession($session);

        $this->reportTime(count($data));
    }
    
    public function printUpdateResponse($response)
    {
        echo PHP_EOL . $response['status'] . PHP_EOL . PHP_EOL;
        if ($response['fieldmap']) {
            foreach ($response['fieldmap'] as $key => $value) {
                echo "$key  --->  $value" . PHP_EOL;
            }
            echo PHP_EOL;
        }
        if ($response['errors']) {
            echo 'ERRORS: ' . PHP_EOL;
            foreach ($response['errors'] as $error) {
                echo $error . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }

    public function checkSkus()
    {
        $skus = $this->getSkusFromCsv();

        $client = $this->soap;
        $session = $client->login($this->api_user, $this->api_key);

        try {
            $response = $client->call($session, 'gxproduct.checkSkus', array($skus));
            foreach ($response as $sku => $status) {
                echo "$sku:  $status" . PHP_EOL;
            }
        } catch (Exception $e) {
            echo "API Fault: " . $e->getMessage() . PHP_EOL;
        }

        $client->endSession($session);
    }

    public function getUpdateFromCsv($filename)
    {
        $filepath = __DIR__ . '/' . $filename;
        $file = fopen($filepath, 'r');
        $data = array();
        $header = fgetcsv($file);
        $sku = 0;
        while (( $line = fgetcsv($file)) !== false ) {
            foreach ($line as $key => $value) {
                if ($key == 0) {
                    $sku = $value;
                } else {
                    $code = $header[$key];
                    $data[$sku][$code] = $value;
                }
            }
        }
        fclose($file);
        return $data;
    }

    public function getSkusFromCsv()
    {
        $filename = __DIR__ . '/' . $this->skus_file;
        $file = fopen($filename, 'r');
        $data = array();
        while (( $line = fgetcsv($file)) !== false ) {
            $data[] = $line[0];
        }
        fclose($file);
        return $data;
    }

    public function startTimer()
    {
        $this->start_time = microtime(true);
    }

    public function stopTimer()
    {
        $this->stop_time = microtime(true);
    }

    public function reportTime($num_products)
    {
        $total_time = round($this->stop_time - $this->start_time, 2);
        $prod_per_sec = round($num_products / $total_time, 2);
        echo "$num_products products processed in $total_time seconds" . PHP_EOL;
        echo "$prod_per_sec products per second" . PHP_EOL;
    }
}
