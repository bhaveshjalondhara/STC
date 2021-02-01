<?php

namespace STC\StcPaymentGateway\Cron;

use \STC\StcPaymentGateway\Model\MobilePaymentFactory;
use \STC\StcPaymentGateway\Helper\Config;
use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Quote\Model\Quote\AddressFactory;
class PaymentInquiry
{

	protected $_mobilePaymentFactory;
	protected $helper;
	protected $_orderRepository;
	protected $quoteAddressFactory;

	public function __construct(
		 MobilePaymentFactory $mobilePaymentFactory,
		 Config $helper,
		 OrderRepositoryInterface $orderRepository,
		 AddressFactory $quoteAddressFactory
		)
		{
			$this->helper = $helper;
			$this->_mobilePaymentFactory = $mobilePaymentFactory;
			$this->_orderRepository = $orderRepository;
			$this->quoteAddressFactory = $quoteAddressFactory;
		}

	public function execute()
	{

		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cron.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
		$logger->info(__METHOD__);
		$this->getStcPaymentData();
		//return $this;

	}

	public function getStcPaymentData()
	{	
		$model = $this->_mobilePaymentFactory->create()
				->getCollection()
				->addFieldToSelect('customer_refnum')
				->addFieldToSelect('order_id')
				->addFieldToSelect('quote_id')
				->addFieldToSelect('id');
		foreach ($model as $key => $model) {
			$data = [
				'RefNum' => $model->getCustomerRefnum()
			];
			$orderID = $model->getOrderId();
			$id = $model->getId();
			$quoteId = $model->getQuoteId();
			$result  = $this->helper->getPaymentInquiry($data);
			if ($result) {
    			$arrresult = json_decode($result,true);
    		  $transactionList	= $arrresult['PaymentInquiryResponseMessage']['TransactionList']; 
    		  $paymentStatusDesc = $transactionList[0]['PaymentStatusDesc'];
    		  $paymentStatus = $transactionList[0]['PaymentStatus'];

    		 try {
	    		  	if($id)
	    		  	{
	    		  		$quoteaddress = $this->quoteAddressFactory->create()
								->getCollection()
								->addFieldToSelect('address_id')
								->addFieldToSelect('quote_id')
								->addFieldToSelect('telephone')
								->addFieldToFilter('quote_id',$quoteId);

							$telephone = '';
							if($quoteaddress->getSize()){
							     $quoteaddressdata = $quoteaddress->getFirstItem();
							     $telephone = $quoteaddressdata->getTelephone();  
							 }


	    		  		$modelLoad = $this->_mobilePaymentFactory->create();
	    		  		$modelLoad->load($id);
	    		  		$modelLoad->setPaymentStatus($paymentStatus);
	    		  		$modelLoad->setPaymentStatusdesc($paymentStatusDesc);
	    		  		$modelLoad->setCustomerMobileno($telephone);

	    		  		$modelLoad->save();
	    		  	}
    		 	 } catch (\Exception $e) {
           		 	$this->messageManager->addErrorMessage(__($e->getMessage()));
      		   	 }
    		
    		}

    		if($model->getOrderId() && $paymentStatus)
    		{
    			//processing
    			$code = '';
    			
				switch ($paymentStatus) {
				    case 1:
				        //Pending = 1,
				   		$code = 'pending';
				        break;
				    case 2:
				    	//Paid = 2
				        $code = 'complete';
				        break;
				    case 4:
				    	//Cancelled = 4,
				        $code = 'canceled';
				        break;
				    case 5:
				    	//Expired = 5
				        $code = 'closed';
				}
				
    			$this->setOrderStatus($orderID, $code);
    		}
           
		}
	}

	public function setOrderStatus($orderID, $statusCode){
        try{
            $order = $this->_orderRepository->get($orderID);
            $order->setState($statusCode)->setStatus($statusCode);
            $this->_orderRepository->save($order);
            return true;
        } catch (\Exception $e){
            return false;
        }
    }
}