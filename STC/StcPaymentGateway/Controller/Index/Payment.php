<?php
namespace STC\StcPaymentGateway\Controller\Index;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Magento\Framework\App\RequestInterface;
use \STC\StcPaymentGateway\Model\MobilePaymentFactory;
use \STC\StcPaymentGateway\Helper\Config;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Checkout\Model\Session;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\Controller\Result\JsonFactory;
use \Magento\Framework\Session\SessionManagerInterface;
use \Magento\Customer\Api\AddressRepositoryInterface;
use \Magento\Framework\App\ResourceConnection;
class Payment extends \Magento\Framework\App\Action\Action
{
	protected $_pageFactory;
	protected $_request;
	protected $_mobilePaymentFactory;
	protected $_helper;
	protected $_scopeConfig;
	protected $_checkoutSession;
	protected $_storeManager;
	protected $_resultJsonFactory;
	protected $_coreSession;
	protected $_customerAddress;
	protected $_resource;

	public function __construct(
		 Context $context,
		 PageFactory $pageFactory,
		 RequestInterface $request,
		 MobilePaymentFactory $mobilePaymentFactory,
		 Config $helper,
		 ScopeConfigInterface $scopeConfig,
		 Session $checkoutSession,
		 StoreManagerInterface $storeManager,
		 JsonFactory $resultJsonFactory,
		 SessionManagerInterface $coreSession,
		 AddressRepositoryInterface $customerAddress,
		 ResourceConnection $resource
		)
	{
		$this->_pageFactory = $pageFactory;
		$this->_request = $request;
		$this->_mobilePaymentFactory = $mobilePaymentFactory;
		$this->_helper = $helper;
		$this->_scopeConfig = $scopeConfig;
		$this->_checkoutSession = $checkoutSession;
		$this->_storeManager = $storeManager;
		$this->_resultJsonFactory = $resultJsonFactory;
		$this->_coreSession = $coreSession;
		$this->_customerAddress = $customerAddress;
		$this->_resource = $resource;

		return parent::__construct($context);
	}

	/**
     * Get store identifier
     *
     * @return  int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

	public function execute()
	{
		/*call the mobilePaymentFactory custom model class*/
		try {
			$storeId = '';
			$quoteId = '';

			$order    = $this->_checkoutSession->getLastRealOrder();
			$shippingData = $this->_checkoutSession->getQuote();
			$quoteId    = $shippingData->getId();
			$connection  = $this->_resource->getConnection();
			$storeId = $this->getStoreId();

			$resultJson = $this->_resultJsonFactory->create();
			$priorityReqParam = $this->_request->getParam('action');
			//$priorityReqParam = 'directpaymentApi';
			if ($priorityReqParam == 'verify_otp') {
				if($this->_coreSession->getdirectPaymentAuthArr()){
					$msgAuthData = $this->_coreSession->getdirectPaymentAuthArr();
				}

				if($this->_request->getParam('otp')){
					$otp = $this->_request->getParam('otp');
				}
				
				$paymentConfirm = [
					 "OtpReference" => $msgAuthData['DirectPaymentAuthorizeResponseMessage']['OtpReference'],
       				     "OtpValue" => $otp,
               "STCPayPmtReference" => $msgAuthData['DirectPaymentAuthorizeResponseMessage']['STCPayPmtReference'],
       			   "TokenReference" => "payment-verify",
        			   "TokenizeYn" => true
				];

				$resultPaymentConfirm = $this->_helper->directPaymentConfirm($paymentConfirm);

				if ($resultPaymentConfirm) {
					$confirmResultArr = json_decode($resultPaymentConfirm,true);
					return $resultJson->setData($confirmResultArr);
				} else {
					return $resultJson->setData(['error'=>'resultPaymentConfirm data is not found.']);
				}
				
				
			} elseif ($priorityReqParam == 'directPaymentConfirm') {
				$directPaymentConfirm = $this->_request->getParam('paymentconfirm');
				$paymentConfirmJsonDecode = json_decode(json_encode($directPaymentConfirm),true);
				$quoteinfo = $this->_coreSession->getquoteinfo();

				/** confirm payment data */
				$confdata = [
					'bill_number' => 'billnumber',
					'customer_refnum' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['RefNum'],
					'customer_order_amount' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['Amount'],
					'customer_mobileno' => $quoteinfo['telephone'],
					'store_id' => $storeId,
					'order_id' => '',
					'payment_status' => '',
					'payment_statusdesc' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['PaymentStatusDesc'],
					'quote_id' => $quoteId,
					'customer_stcpayrefnum' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['STCPayRefNum'],
					'customer_tokenid' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['TokenId'],
					'payment_date' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['PaymentDate'],
					'branch_id' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['BranchID'],
					'teller_id' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['TellerID'],
					'device_id' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['DeviceID'],
					'merchant_id' => $paymentConfirmJsonDecode['DirectPaymentConfirmResponseMessage']['MerchantID'],
					'customer_id' => $quoteinfo['customerid'],
				];

				try {
					if($confdata){
						$model = $this->_mobilePaymentFactory->create();
						$model->setData($confdata)->save();
					} 
				} catch (\Exception $e) {
		            $this->messageManager->addErrorMessage(__($e->getMessage()));
		        } 
				//return $resultJson->setData($directPaymentConfirm);
				return $resultJson->setData($confdata);
			} elseif ($priorityReqParam == 'afterPlaceOrder') {
		       $data = ["order_id"=>$order->getId()]; // Key_Value Pair
		       $where = ['quote_id = ?' => (int)$order->getQuoteId()];
		       $tableName = $connection->getTableName("stc_paymentgateway");
		       $connection->update($tableName, $data, $where);
			} elseif ($priorityReqParam == 'directpayment') {
				$customerId = '';
				if($quoteId && !empty($quoteId)){
					$quoteInformation = $this->getQuoteInfo($quoteId);
					if(!empty($quoteInformation['customerid']))
						{
							$customerId = $quoteInformation['customerid'];
						}
				}

				 try
		        {
		          if(!empty($customerId))
		          {
		          	$sql = "SELECT `stc_paymentgateway`.customer_tokenid FROM `stc_paymentgateway` where customer_id = " . $customerId . " ORDER BY `id` DESC";
	        	    $resultAddress = $connection->fetchAll($sql);
	        	    if ($resultAddress) {
	        	    	$customerTokenid = '';
	        	    	$customerTokenid = $resultAddress[0]['customer_tokenid'];
	        	    	if($customerTokenid && !empty($customerTokenid))
	        	    	{
	        	    		return $resultJson->setData(['success'=>'success','tokenId'=>$customerTokenid]);
	        	    	} else {
	        	    		return $resultJson->setData(['error'=>'exception','message'=>'can not get token Id']);
	        	    	}
		             	
		          	} else {

		          		return $resultJson->setData(['error'=>'exception','message'=>'can not get token Id']);
		          	}
		          }

				}catch (\Exception $e) {
		            return $resultJson->setData(['error'=>$e->getMessage()]);
		        } 

			} elseif ($priorityReqParam == 'directpaymentApi') {
				$customerId = '';

				$grandTotal = $shippingData->getGrandTotal();
				try{
					if ($quoteId && !empty($quoteId)) {
						$quoteInformation = $this->getQuoteInfo($quoteId);

						if(!empty($quoteInformation['customerid']))
						{
							$customerId = $quoteInformation['customerid'];
						}
					}

				}catch (\Exception $e) {
		            return $resultJson->setData(['error'=>$e->getMessage()]);
		        } 

		         try
		        {
		          if(!empty($customerId))
		          {

		          	$sql = "SELECT `stc_paymentgateway`.customer_tokenid FROM `stc_paymentgateway` where customer_id = " . $customerId . " ORDER BY `id` DESC";
	        	    $resultAddress = $connection->fetchAll($sql); 
	        	    if ($resultAddress) {
	        	    	$customerTokenid = '';
	        	    	$customerTokenid = $resultAddress[0]['customer_tokenid'];

	        	    	if($customerTokenid && !empty($customerTokenid))
	        	    	{
	        	        	$directPayment = $this->applyDirectPayment($customerTokenid,$grandTotal,$quoteInformation,$storeId,$quoteId);
	        	    	 return $directPayment;
	        	    	}
		             	
		          	}
		          }

				}catch (\Exception $e) {
		            return $resultJson->setData(['error'=>$e->getMessage()]);
		        } 


			} else {
				
				$customerId = '';
				 $telephone = '';
				$customName = '';
				$grandTotal = '';


				$grandTotal = $shippingData->getGrandTotal();
				try{
					if ($quoteId && !empty($quoteId)) {
						$quoteInformation = $this->getQuoteInfo($quoteId);

						if(!empty($quoteInformation['customerid']))
						{
							$customerId = $quoteInformation['customerid'];
						}

						if(!empty($quoteInformation['customName']))
						{
							$customName = $quoteInformation['customName'];
						}

						if(!empty($quoteInformation['telephone']))
						{
							$telephone = $quoteInformation['telephone'];
						}

    					$this->_coreSession->unsquoteinfo();
	    			    $this->_coreSession->setquoteinfo($quoteInformation);
					}

				}catch (\Exception $e) {
		            return $resultJson->setData(['error'=>$e->getMessage()]);
		        } 

				$data = [
					'customer_refnum'       => $customName.'_'.$quoteId,
					'customer_order_amount' => $grandTotal,
					'bill_number'           => strtoupper(substr($customName,0, 3)) . $quoteId,
					'customer_mobileno'     => $telephone,
					//'customer_mobileno'     => '966539396141',
					'merchant_note'         => 'success',
					'authorization_reference' => '',
					'expiry_date'			  => '',
					'store_id'				=> $storeId,
				];
 				
	    		$reqParam = $this->_helper->setArrParam($data);	
	    		if ($reqParam) {
	    			/*call the API*/
	    			$result = $this->_helper->mobilePaymentAuthorize($reqParam);
	    		}
	    		if ($result && !empty($result)) {
	    			$arrresult = json_decode($result,true);
	    			
	    			if ($arrresult) {
	    				$this->_coreSession->unsdirectPaymentAuthArr();
	    			    $this->_coreSession->setdirectPaymentAuthArr($arrresult);
	    			    return $resultJson->setData($arrresult);
	    			} else {
	    				return $resultJson->setData(['error'=>'STC Server Error in Application.']);
	    			}

				} else {
					return $resultJson->setData(['error'=>'STC Server Error in Application.']);
				}
				
		 }
		} catch (\Exception $e) {

            return $resultJson->setData(['error'=>$e->getMessage()]);
        } 
		
	}

	public function getQuoteInfo($quoteId)
	{
		$connection  = $this->_resource->getConnection();
		$sql = "SELECT * FROM `quote_address` where quote_id = " . $quoteId . " ORDER BY `address_id` DESC";
    	$resultAddress = $connection->fetchAll($sql); 
		$telephone = $resultAddress[0]['telephone'];
		$customName = $resultAddress[0]['firstname'];
		$customerId = '';
		 if($resultAddress[0]['customer_id']
		   && $resultAddress[0]['customer_id'] !='' 
		   && $resultAddress[0]['customer_id']!= NULL)
		{
			$customerId = $resultAddress[0]['customer_id'];
		}
		
		$quoteCustomerInfo = [
			'telephone' => $telephone,
			'customerid' => $customerId,
			'customName' => $customName
		];

		return $quoteCustomerInfo;
	}

	public function applyDirectPayment($customerTokenid,$grandTotal,$quoteCustomerInfo,$storeId,$quoteId)
	{
    	try {	
    		$customName = '';
    		$resultDirect = '';
    		
    		$customerid = '';
    		
    		if(!empty($quoteCustomerInfo['customName'])){
    			$customName = $quoteCustomerInfo['customName'];
    		}

    		if(!empty($quoteCustomerInfo['customerid'])){
    			$customerid = $quoteCustomerInfo['customerid'];
    		}

    		$resultJson = $this->_resultJsonFactory->create();

    		if ($customerTokenid && !empty($customerTokenid)) {
    			$directPaymentdata = [
    				"refNum"         => $customName.'_'.rand(10,100),
					"billNumber"     => strtoupper(substr($customName,0, 3)) . rand(10,100),
					"billDate"       => date("Y-m-d h:i:sa"), 
					"amount"         => $grandTotal,
					"tokenId"        => $customerTokenid,
				];
				$directreqParam = $this->_helper->setDirectArrParam($directPaymentdata);	
	    		if ($directreqParam) {
	    			/*call the API*/
	    			$resultDirect = $this->_helper->directPaymentApi($directreqParam);
	    		}

			     if ($resultDirect && !empty($resultDirect)) {
	    			$arrdirectresult = json_decode($resultDirect,true);
	    			if ($arrdirectresult) {
	    				/** confirm payment data */
	    				//print_r($arrdirectresult['DirectPaymentResponseMessage']); exit();
					$directPaymentconfdata = [
						'bill_number' => 'billnumber',
						'customer_refnum' => $arrdirectresult['DirectPaymentResponseMessage']['RefNum'],
						'customer_order_amount' => $arrdirectresult['DirectPaymentResponseMessage']['Amount'],
						'customer_mobileno' => $quoteCustomerInfo['telephone'],
						'store_id' => $storeId,
						'order_id' => '',
						'payment_status' => $arrdirectresult['DirectPaymentResponseMessage']['PaymentStatus'],
						'payment_statusdesc' => $arrdirectresult['DirectPaymentResponseMessage']['PaymentStatusDesc'],
						'quote_id' => $quoteId,
						'customer_stcpayrefnum' => $arrdirectresult['DirectPaymentResponseMessage']['STCPayRefNum'],
						'customer_tokenid' => $customerTokenid,
						'payment_date' => $arrdirectresult['DirectPaymentResponseMessage']['PaymentDate'],
						'branch_id' => $arrdirectresult['DirectPaymentResponseMessage']['BranchID'],
						'teller_id' => $arrdirectresult['DirectPaymentResponseMessage']['TellerID'],
						'device_id' => $arrdirectresult['DirectPaymentResponseMessage']['DeviceID'],
						'merchant_id' => $arrdirectresult['DirectPaymentResponseMessage']['MerchantID'],
						'customer_id' => $customerid,
					];
			
					try {
						if($directPaymentconfdata){
							$model = $this->_mobilePaymentFactory->create();
							$model->setData($directPaymentconfdata)->save();
						} 
					} catch (\Exception $e) {
			            $this->messageManager->addErrorMessage(__($e->getMessage()));
			        } 
	    			    return $resultJson->setData($arrdirectresult);
	    			} else {
	    				return $resultJson->setData(['error'=>'STC Server Error in Application.']);
	    			}

				} else {
					return $resultJson->setData(['error'=>'STC Server Error in Application.']);
				}
    		}
    	} catch (\Exception $e) {
          return $resultJson->setData(['error'=> $e->getMessage()]);
        } 
	}
}