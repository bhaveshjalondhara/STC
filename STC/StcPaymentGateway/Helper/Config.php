<?php
namespace STC\StcPaymentGateway\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
class Config extends AbstractHelper
{
	const BRANCH_ID = 'payment/stcpayment/branchid';
    const TELLER_ID = 'payment/stcpayment/tellerid';
    const DEVICE_ID = 'payment/stcpayment/deviceid';
    const REFNUM = 'GhalyRefNo_1006221111';
    const AMOUNT = '20';
    const BILL_NUMBER = 'GRN2';
    const MOBILE_NO = '966509580055';
    const MERCHANTNOTE = 'Ghaly Test';
    const EXPIRY_PERIOD_TYPE = 'payment/stcpayment/expiryperiodtype';
    const EXPIRY_PERIOD = 'payment/stcpayment/expiryperiod';
    const X_CLIENTCODE = 'payment/stcpayment/x_clientcode';

    const SSLKEY = 'key.txt'; 
    const SSLCRT = 'c8dbb8766476d2a7.crt'; 

    const MOBILEPAYMENTAUTHORIZE = 'payment/stcpayment/directpaymentauthorize';
    const DIRECTPAYMENTCONFIRM = 'payment/stcpayment/directpaymentconfirm';
    const DIRECTPAYMENT  = 'payment/stcpayment/directpayment';
    const PAYMENTINQUIRY = 'payment/stcpayment/paymentinquiry';

    protected $directoryList;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;



 public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList
    ) {
        $this->scopeConfig = $context->getScopeConfig();
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }
   
     public function setArrParam($data)
     {
        $paramData = [
             'BranchID'           => $this->getConfigFileName(self::BRANCH_ID),
             'TellerID'           => $this->getConfigFileName(self::TELLER_ID),
             'DeviceID'           => $this->getConfigFileName(self::DEVICE_ID),
             'RefNum'             => $data['customer_refnum'],
             'BillNumber'         => $data['bill_number'],
             'MobileNo'           => $data['customer_mobileno'],
             'Amount'             => $data['customer_order_amount'],
             'MerchantNote'       => $data['merchant_note']
        ];
        return $paramData;
      }

      public function setDirectArrParam($data)
      {
         $directPaymentData = [
              "BranchID" => $this->getConfigFileName(self::BRANCH_ID),
              "TellerID" => $this->getConfigFileName(self::TELLER_ID),
              "RefNum" => $data['refNum'],
              "BillNumber" => $data['billNumber'],
              "BillDate" => $data['billDate'],
              "Amount" => $data['amount'],
              "MerchantNote" => "directpayment",
              "TokenId" => $data['tokenId']
        ];
        return $directPaymentData;

      }

    public function getMainArrParam($arrdata)
    {
        $arrParam = [];
        $arrParam['DirectPaymentAuthorizeRequestMessage'] = $arrdata;
        return $arrParam;
    }

    public function getDirectMainArrParam($arrdata)
    {
        $arrParam = [];
        $arrParam['DirectPaymentRequestMessage'] = $arrdata;
        return $arrParam;
    }

    public function mobilePaymentAuthorize($arrParam) 
    {
         $mainArr  = $this->getMainArrParam($arrParam);
         $json = json_encode($mainArr);
         $ret = $this->curl($this->getMobilePaymentAuthorizeUrl(), $json, $http_status);
         return $ret;
    }

     public function directPaymentApi($arrParam) 
    {
         $mainArr  = $this->getDirectMainArrParam($arrParam);
         $json = json_encode($mainArr);
         $ret = $this->curl($this->getDirectPaymentUrl(), $json, $http_status);
         return $ret;
    }

    public function directPaymentConfirm($arrdata)
    {
        $arrParam = [];
        $arrParam['DirectPaymentConfirmRequestMessage'] = $arrdata;
        $json = json_encode($arrParam );
        $paymentinquiry = $this->curl($this->getDirectPaymentConfirmUrl(), $json, $http_status);
        return $paymentinquiry;
    }

    public function getPaymentInquiry($arrdata)
    {
        $arrParam = [];
        $arrParam['PaymentInquiryRequestMessage'] = $arrdata;
        $json = json_encode($arrParam );
        $paymentinquiry = $this->curl($this->getPaymentInguiryUrl(), $json, $http_status);
         return $paymentinquiry;
    }

    public function getXClientcode()
    {
      return  $this->getConfigFileName(self::X_CLIENTCODE);
    }

    function curl($url, $post_data, &$http_status, &$header = null) {

        $pubMediaDir = $this->directoryList->getPath(DirectoryList::MEDIA);
        $ds = DIRECTORY_SEPARATOR;
        $dirTest = '/ssl/default';

        $sslkey = $pubMediaDir . $dirTest . $ds . self::SSLKEY;
        $sslcrt = $pubMediaDir . $dirTest . $ds . self::SSLCRT;

        $ch=curl_init();
        curl_setopt($ch, CURLOPT_USERPWD, "username:passwd");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        if (!is_null($header)) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','X-ClientCode:'.$this->getXClientcode()));
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSLKEY, $sslkey);
        curl_setopt($ch, CURLOPT_SSLCERT, $sslcrt);

        $response = curl_exec($ch);
        $body = null;
        if (!$response) {
            $body = curl_error($ch);
            $http_status = -1;
        } else {
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (!is_null($header)) {
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
            } else {
                $body = $response;
            }
        }

        curl_close($ch);
        return $body;
    }

    /**
     * @param string $configPath
     * @return bool
     */
    public function getScopeConfig($configPath)
    {
        return $this->scopeConfig->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getConfigFileName($nameFile)
    {
        return $this->getScopeConfig($nameFile);
    }

    public function getMobilePaymentAuthorizeUrl()
    {
        return $this->getConfigFileName(self::MOBILEPAYMENTAUTHORIZE);
    }

    public function getPaymentInguiryUrl()
    {
        return $this->getConfigFileName(self::PAYMENTINQUIRY);
    }

    public function getDirectPaymentConfirmUrl()
    {
        return $this->getConfigFileName(self::DIRECTPAYMENTCONFIRM);
    }

     public function getDirectPaymentUrl()
    {
        return $this->getConfigFileName(self::DIRECTPAYMENT);
    }

}