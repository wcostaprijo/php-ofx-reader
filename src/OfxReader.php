<?php

namespace WcDeveloper\PhpOfxReader;

use Carbon\Carbon;

class OfxReader
{
    private $context;

    private $template;

    /**
     * Init instance of reader file
     *
     * @param string $file | path or url 
     * @param string $templateClass | class of template
     */
    public function __construct($file, $templateClass = null)
    {
        $this->context = file_get_contents($file);

        if(empty($templateClass)) {
            $templateClass = __NAMESPACE__ . '\\Generico';
        }
        
        if (!class_exists($templateClass)) {
            throw new \Exception(sprintf('O template "%s" não existe.', $templateClass));
        }

        $this->template = new $templateClass();
    }

    public function getTransactions()
    {
        $xml = $this->loadFromString($this->context);
        $mapTransactions = explode('->', $this->template->mapTransactions());
        $transactions = [];
        foreach($mapTransactions as $i => $map) {
            if($i == (count($mapTransactions) - 1)) {
                foreach($xml->$map as $transaction) {
                    $array = [];
                    foreach($this->template->mapTransaction() as $key => $mapData) {
                        $field = $mapData['key'];
                        if($mapData['type'] == 'date') {
                            $array[$key] = $this->createDateTimeFromStr((string) $transaction->$field);
                        }else if($mapData['type'] == 'money') {
                            $array[$key] = $this->createAmountFromStr((string) $transaction->$field);
                        }else{
                            $array[$key] = str_replace('|-|-|-|-|-|', '&', trim((string) $transaction->$field));
                        }
                    }
                    if(!empty($array)) {
                        $transactions[] = $array;
                    }
                }
            }else{
                $xml = $xml->$map;
            }
        }
        return $transactions;
    }

    /**
     * Load an XML string without PHP errors - throws exception instead
     *
     * @param string $xmlString
     * @throws \RuntimeException
     * @return \SimpleXMLElement
     */
    private function xmlLoadString($xmlString)
    {
        libxml_clear_errors();
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($errors = libxml_get_errors()) {
            throw new \RuntimeException('Failed to parse OFX: ' . var_export($errors, true));
        }

        return $xml;
    }

    /**
     * Load an OFX by directly using the text content
     *
     * @param string $ofxContent
     * @return  Ofx
     */
    public function loadFromString($ofxContent)
    {
        $ofxContent = str_replace(["\r\n", "\n", "\r"], "", $ofxContent);
        $ofxContent = str_replace(["\t", ""], "", $ofxContent);
        $ofxContent = mb_convert_encoding($ofxContent, 'UTF-8', mb_detect_encoding($ofxContent));

        $sgmlStart = stripos($ofxContent, '<'.$this->template->rootTag().'>');
        $ofxHeader =  trim(substr($ofxContent, 0, $sgmlStart));

        $ofxSgml = trim(substr($ofxContent, $sgmlStart));
        return $this->xmlLoadString($this->prepareOfxXml($ofxSgml));
    }

    /**
     * Detect if the OFX file is on one line. If it is, add newlines automatically.
     *
     * @param string $ofxContent
     * @return string
     */
    private function prepareOfxXml($ofxContent)
    {
        $mapTransactions = explode('->', $this->template->mapTransactions());
        $xml = $ofxContent;
        foreach($mapTransactions as $i => $map) {
            if(preg_match('/<'.$map.'>.*<\/'.$map.'>/', $xml, $result) === 1) {
                $xml = $result[0];
            }
        }

        $lastMap = array_reverse($mapTransactions)[0];
        $xml = str_replace('</'.$lastMap.'>', '</'.$lastMap.">\n", $xml);
        preg_match_all('/<'.$lastMap.'>(.*?)<\/'.$lastMap.'>/', $xml, $result);
        $mapTransaction = $this->template->mapTransaction();
        $mapTransactionKeys = array_keys($mapTransaction);
        $xmlString = '';
        foreach($result[1] as $res) {
            $lineTransaction = '';
            $i = 1;
            foreach($mapTransaction as $mapData) {
                $map = $mapData['key'];
                preg_match_all('/'.$map.'>/', $res, $matches);
                if (isset($matches[0]) and count($matches[0]) > 0) {
                    $currentLine = explode('<'.$map.'>', $res);
                    $nextTagPos = strpos($currentLine[1],'<');
                    if($nextTagPos === false){
                        $lineTransaction .= str_pad("",count($mapTransactions) + 1," ")."<".$map.'>'.str_replace('&','|-|-|-|-|-|', $currentLine[1]).'</'.$map.">\n";
                    }else{
                        $lineTransaction .= str_pad("",count($mapTransactions) + 1," ")."<".$map.'>'.str_replace('&','|-|-|-|-|-|', substr($currentLine[1], 0, $nextTagPos)).'</'.$map.">\n";
                    }
                }
                $i++;
            }
            $xmlString .= str_pad("",count($mapTransactions)," ")."<".$lastMap.">\n".$lineTransaction.str_pad("",count($mapTransactions)," ")."</".$lastMap.">\n";
        }

        foreach(array_reverse($mapTransactions) as $i => $map) {
            if($i > 0) {
                $xmlString = str_pad("",count($mapTransactions) - $i," ")."<".$map.">\n".$xmlString.str_pad("",count($mapTransactions) - $i," ")."</".$map.">\n";
            }
        }

        return "<".$this->template->rootTag().">\n".$xmlString."</".$this->template->rootTag().">";
    }

    /**
     * Create a DateTime object from a valid OFX date format
     *
     * Supports:
     * YYYYMMDDHHMMSS.XXX[gmt offset:tz name]
     * YYYYMMDDHHMMSS.XXX
     * YYYYMMDDHHMMSS
     * YYYYMMDD
     *
     * @param  string $dateString
     * @param  boolean $ignoreErrors
     * @return \DateTime $dateString
     * @throws \Exception
     */
    private function createDateTimeFromStr($dateString, $dateFormat = 'Y-m-d', $ignoreErrors = false)
    {
        if (!isset($dateString) || trim($dateString) === '') {
            return null;
        }

        $regex = '/'
            . "(\d{4})(\d{2})(\d{2})?"     // YYYYMMDD             1,2,3
            . "(?:(\d{2})(\d{2})(\d{2}))?" // HHMMSS   - optional  4,5,6
            . "(?:\.(\d{3}))?"             // .XXX     - optional  7
            . "(?:\[(-?\d+)\:(\w{3}\]))?"  // [-n:TZ]  - optional  8,9
            . '/';

        if (preg_match($regex, $dateString, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];
            $hour = isset($matches[4]) ? $matches[4] : 0;
            $min = isset($matches[5]) ? $matches[5] : 0;
            $sec = isset($matches[6]) ? $matches[6] : 0;

            $format = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min . ':' . $sec;

            try {
                return Carbon::parse($format)->format($dateFormat);
            } catch (\Exception $e) {
                if ($ignoreErrors) {
                    return null;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Failed to initialize DateTime for string: ' . $dateString);
    }

    /**
     * Create a formatted number in Float according to different locale options
     *
     * Supports:
     * 000,00 and -000,00
     * 0.000,00 and -0.000,00
     * 0,000.00 and -0,000.00
     * 000.00 and 000.00
     *
     * @param  string $amountString
     * @return float
     */
    private function createAmountFromStr($amountString)
    {
        $valor = str_replace(['R$','.',',',' '],'',$amountString);
        return doubleval(substr_replace($valor, '.', strlen($valor) - 2, 0));
    }
}
