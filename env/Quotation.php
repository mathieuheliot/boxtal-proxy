<?php
/** 
 * EnvoiMoinsCher API quotation class.
 * 
 * The class is used to obtain informations about a quotation and, if possibly, order it.
 * @package Env
 * @author EnvoiMoinsCher <dev@envoimoinscher.com>
 * @version 1.0
 */

class Env_Quotation extends Env_WebService {

  /** 
   *  Public variable represents offers array. 
	 *  Organisation :
	 *	$offers[x] 					=> array(
	 *  	['mode'] 						=> data
	 *  	['url'] 						=> data
	 *  	['operator'] 				=> array(
	 *			['code'] 						=> data
	 *			['label'] 					=> data
	 *			['logo']						=> data
	 *		)
	 *  	['service'] 				=> array(
	 *			['code'] 						=> data
	 *			['label'] 					=> data
	 *		)
	 *  	['price'] 					=> array(
	 *			['currency'] 				=> data
	 *			['tax-exclusive'] 	=> data
	 *			['tax-inclusive']		=> data
	 *		)
	 *  	['collection'] 			=> array(
	 *			['type'] 						=> data
	 *			['date'] 						=> data
	 *			['label']						=> data
	 *		)
	 *  	['delivery'] 				=> array(
	 *			['type'] 						=> data
	 *			['date'] 						=> data
	 *			['label']						=> data
	 *		)
	 *  	['characteristics'] => data
	 *  	['alert'] 					=> data
	 *  	['mandatory'] 			=> array([...])
	 *  )
   *  @access public
   *  @var array
   */
  public $offers = array();
	
  /** 
   *  Public array containing order informations like order number, order date...
   *  @access public
   *  @var array
   */
  public $order = array();

  /** 
   *  Protected variable with pallet dimensions accepted by EnvoiMoinsCher.com. The dimensions are given
   *  in format "length cm x width cm". They are sorted from the longest to the shortest.
   *  <br />To pass a correct pallet values, use the $palletDimss' key in your "pallet" parameter.
   *  For exemple : 
   *  $quotInfo = array("collecte_date" => "2015-04-29", "delay" => "aucun",  "content_code" => 10120,
   *  <b>"pallet" => 130110</b>);
   *  <br />$this->makeOrder($quotInfo, true); 
   *  @access protected
   *  @var array
   */
  protected $palletDims = array(130110 => '130x110', 122102 => '122x102', 120120 => '120x120', 120100 => '120x100',
                           12080 => '120x80' , 114114 => '114x114', 11476 => '114x76', 110110 => '110x110',
                           107107 => '107x107', 8060 => '80x60'
                          );

  /** 
   *  Protected variable with shipment reasons. It is used to generate proforma invoice.
   *  Exemple of utilisation : 
   *  $quotInfo = array("collecte_date" => "2015-04-29", "delay" => "aucun",  "content_code" => 10120,
   *  "operator" => "UPSE", <b>"reason" => "repair");
   *  $this->makeOrder($quotInfo, true);
   *  @access protected
   *  @var array
   */
  protected $shipReasons = array('sale' => 'sale', 'repair' => 'repr', 'return' => 'rtrn', 'gift' => 'gift',
                           'sample' => 'smpl' , 'personnal' => 'prsu', 'document' => 'icdt', 'other' => 'othr');

  /** 
   *  Public setter used to pass proforma parameters into the api request.
   *  You must pass a multidimentional array, even for one line.
   *  The array keys must start with 1, not with 0.
   *  Exemple : 
   *  $this->setProforma(array(1 => array("description_en" => "english description for this item",
   *  "description_fr" => "la description franšaise pour ce produit", "origine" => "FR", 
   *  "number" => 2, "value" => 500)));
   *  The sense of keys in the proforma array : 
   *   - description_en => description of your item in English
   *   - description_fr => description of your item in French
   *   - origine => origin of your item (you can put EEE four every product which comes 
   *     from EEA (European Economic Area))
   *   - number => quantity of items which you send
   *   - value => unitary value of one item 
   *  @access public
   *  @param array $data Array with proforma informations.
   *  @return void
   */
  public function setProforma($data) {
    foreach($data as $key => $value) {
      foreach($value as $lineKey => $lineValue) {
        $this->param['proforma_'.$key.'.'.$lineKey] = $lineValue;
      }
    }
  }

  /** 
   *  Function which sets informations about package. 
   *  Please note that if you send the pallet cotation, you can't indicate the dimensions like for
   *  other objects. In this case, you must pass the key from $palletDims protected variable. If the key
   *  is not passed, the request will return an empty result. 
   *  @access public
   *  @param string $type Type : pli, colis, encombrant, palette.
   *  @param array $data Array with package informations : weight, length, width and height.
   *  @return void
   */
  public function setType($type, $dimensions) {
    foreach($dimensions as $d => $data) {
      $this->param[$type.'_'.$d.'.poids'] = $data['poids'];
      if($type == 'palette') {
        $palletDim = explode('x', $this->palletDims[$data['palletDims']]);
        $data[$type.'_'.$d.'.longueur'] = (int)$palletDim[0];
        $data[$type.'_'.$d.'.largeur'] = (int)$palletDim[1];
      }
      $this->param[$type.'_'.$d.'.longueur'] = $data['longueur'];
      $this->param[$type.'_'.$d.'.largeur'] = $data['largeur'];
      if($type != 'pli') {
        $this->param[$type.'_'.$d.'.hauteur'] = $data['hauteur'];
      }
    }
  }

  /** 
   *  Public function which sets shipper and recipient objects.
   *  @access public
   *  @param string $type Person type (shipper or recipient).
   *  @param array $data Array with person informations.
   *  @return void
   */
  public function setPerson($type, $data) {
    foreach($data as $key => $value) {
      $this->param[$type.'.'.$key] = $value;
    }
  }

  /** 
   *  Public function which receives the quotation. 
   *  @access public
   *  @param array $data Array with quotation demand informations (date, type, delay and insurance value).
   *  @return true if request was executed correctly, false if not
   */
  public function getQuotation($quotInfo) {
    $this->param = array_merge($this->param, $quotInfo);
    $this->setGetParams(array());
    $this->setOptions(array('action' => '/api/v1/cotation'));
    return $this->doSimpleRequest();
  }

  /** 
   *  Function which gets quotation details.
   *  @access private
   *  @return false if server response isn't correct; true if it is
   */
  private function doSimpleRequest() {
    $source = parent::doRequest();		
		/* Uncomment if ou want to display the XML content */
		//echo '<textarea>'.$source.'</textarea>';
		
		/* We make sure there is an XML answer and try to parse it */
    if($source !== false) {
      parent::parseResponse($source);
      return (count($this->respErrorsList) == 0);
    }
    return false;
  }

  /** 
   *  Function load all offers
   *  @access public
   *  @param bool $onlyCom If true, we have to get only offers in the "order" mode.
   *  @return void
   */
  public function getOffers($onlyCom = false) {
    $offers = $this->xpath->query('/cotation/shipment/offer');
    foreach($offers as $o => $offer) {
      $offerMode = $this->xpath->query('./mode',$offer)->item(0)->nodeValue;
      if(!$onlyCom || ($onlyCom && $offerMode == 'COM')) {
        
        // Mandatory informations - you must fill it up when you want to order this offer
        $informations = $this->xpath->query('./mandatory_informations/parameter',$offer);
				$mandInfos = array();
        foreach($informations as $m => $mandatory) {
          $arrKey = $this->xpath->query('./code',$mandatory)->item(0)->nodeValue;
          $mandInfos[$arrKey] = array();
          foreach($mandatory->childNodes as $mc => $mandatoryChild) {
            $mandInfos[$arrKey][$mandatoryChild->nodeName] = trim($mandatoryChild->nodeValue);
            if($mandatoryChild->nodeName == 'type') {
              foreach($mandatoryChild->childNodes as $node) {
								if($node->nodeName == 'enum') {
                  $mandInfos[$arrKey][$mandatoryChild->nodeName] = 'enum';
                  $mandInfos[$arrKey]['array'] = array();
                  foreach($node->childNodes as $child) {
                    if(trim($child->nodeValue) != '') {
                      $mandInfos[$arrKey]['array'][] = $child->nodeValue;
                    }
                  }
                }
                else {
                  $mandInfos[$arrKey][$mandatoryChild->nodeName] = $node->nodeName;
                }
              }
            }
          }
          unset($mandInfos[$arrKey]['#text']);
        }

        $charactDetail = $this->xpath->query('./characteristics/label',$offer);
        $charactArray = array();
        foreach($charactDetail as $c => $char) {
           $charactArray[$c] = $char->nodeValue;
        }
				
				$alert = '';
        $alertNode = $this->xpath->query('./alert',$offer)->item(0);
        if(!empty($alertNode)) {
          $alert = $alertNode->nodeValue;
        }
				else
				{
					$alert = '';
				}
				
        $this->offers[$o] = array(
          'mode' => $offerMode,
          'url' => $this->xpath->query('./url',$offer)->item(0)->nodeValue,
          'operator' => array(
            'code' => $this->xpath->query('./operator/code',$offer)->item(0)->nodeValue,
            'label' => $this->xpath->query('./operator/label',$offer)->item(0)->nodeValue,
            'logo' => $this->xpath->query('./operator/logo',$offer)->item(0)->nodeValue 
          ),
          'service' => array(
            'code' => $this->xpath->query('./service/code',$offer)->item(0)->nodeValue,
            'label' => $this->xpath->query('./service/label',$offer)->item(0)->nodeValue
          ), 
          'price' => array(
            'currency' => $this->xpath->query('./price/currency',$offer)->item(0)->nodeValue,
            'tax-exclusive' => $this->xpath->query('./price/tax-exclusive',$offer)->item(0)->nodeValue,
            'tax-inclusive' => $this->xpath->query('./price/tax-inclusive',$offer)->item(0)->nodeValue
          ), 
          'collection' => array(
            'type' => $this->xpath->query('./collection/type/code',$offer)->item(0)->nodeValue,
            'date' => $this->xpath->query('./collection/date',$offer)->item(0)->nodeValue,
            'label' => $this->xpath->query('./collection/type/label',$offer)->item(0)->nodeValue
          ),
          'delivery' => array(
            'type' => $this->xpath->query('./delivery/type/code',$offer)->item(0)->nodeValue,
            'date' => $this->xpath->query('./delivery/date',$offer)->item(0)->nodeValue,
            'label' => $this->xpath->query('./delivery/type/label',$offer)->item(0)->nodeValue
          ),
          'characteristics' => $charactArray,
          'alert' => $alert,
          'mandatory' => $mandInfos
        );
      }
    }
  }

  /** 
   *  Get order informations about collection, delivery, offer, price, service, operator, alerts
   *  and characteristics.
   *  @access private
   *  @return void
   */
  private function getOrderInfos() {
		$order = $this->xpath->query('/cotation/shipment/offer')->item(0);
    $this->order['url'] = $this->xpath->query('./url',$order)->item(0)->nodeValue;
    $this->order['mode'] = $this->xpath->query('./mode',$order)->item(0)->nodeValue;
    $this->order['offer']["operator"]["code"] = $this->xpath->query('./operator/code',$order)->item(0)->nodeValue;
    $this->order['offer']['operator']['label'] = $this->xpath->query('./operator/label',$order)->item(0)->nodeValue;
    $this->order['offer']['operator']['logo'] = $this->xpath->query('./operator/logo',$order)->item(0)->nodeValue;
    $this->order['service']['code'] = $this->xpath->query('./service/code',$order)->item(0)->nodeValue;
    $this->order['service']['label'] = $this->xpath->query('./service/label',$order)->item(0)->nodeValue;
    $this->order['price']['currency'] = $this->xpath->query('./service/code',$order)->item(0)->nodeValue;
    $this->order['price']['tax-exclusive'] = $this->xpath->query('./price/tax-exclusive',$order)->item(0)->nodeValue;
    $this->order['price']['tax-inclusive'] = $this->xpath->query('./price/tax-inclusive',$order)->item(0)->nodeValue;
    $this->order['collection']['code'] = $this->xpath->query('./collection/type/code',$order)->item(0)->nodeValue;
    $this->order['collection']['type_label'] = $this->xpath->query('./collection/type/label',$order)->item(0)->nodeValue;
    $this->order['collection']['date'] = $this->xpath->query('./collection/date',$order)->item(0)->nodeValue;
    $this->order['collection']['time'] = $this->xpath->query('./collection/time',$order)->item(0)->nodeValue;
    $this->order['collection']['label'] = $this->xpath->query('./collection/label',$order)->item(0)->nodeValue;
    $this->order['delivery']['code'] = $this->xpath->query('./delivery/type/code',$order)->item(0)->nodeValue;
    $this->order['delivery']['type_label'] = $this->xpath->query('./delivery/type/label',$order)->item(0)->nodeValue;
    $this->order['delivery']['date'] = $this->xpath->query('./delivery/date',$order)->item(0)->nodeValue;
    $this->order['delivery']['time'] = $this->xpath->query('./delivery/time',$order)->item(0)->nodeValue;
    $this->order['delivery']['label'] = $this->xpath->query('./delivery/label',$order)->item(0)->nodeValue;
    $this->order['proforma'] = $this->xpath->query('./proforma',$order)->item(0)->nodeValue;
    $this->order['alerts'] = array(); 
    $alertsNodes = $this->xpath->query('./alert',$offer);
    foreach($alertsNodes as $a => $alert) {
      $this->order['alerts'][$a] = $alert->nodeValue;  
    }
		$this->order['chars'] = array(); 
    $charNodes = $this->xpath->query('./characteristics/label',$offer);
    foreach($charNodes as $c => $char) {
      $this->order['chars'][$c] = $char->nodeValue;
    }
    $this->order['labels'] = array();
    $labelNodes = $this->xpath->evaluate('./labels/label',$offer);
    foreach($labelNodes as $l => $label) {
      $this->order['labels'][$l] = trim($label->nodeValue);  
    }
  }

  /** 
   *  Public function which sends order request.
   *  If you don't want to pass insurance parameter, you have to make insurance to false
   *  in your parameters array ($quotInfo). It checks also if you pass insurance parameter 
   *  which is obligatory to order a transport service.
   *  The response should contains a order number composed by 10 numbers, 4 letters, 4
   *  number and 2 letters. We use this rule to check if the order was correctly executed 
   *  by API server.
   *  @param $data    : Array with order informations (date, type, delay).
   *  @param $getInfo : Precise if we want to get more informations about order.
   *  @return boolean : True if order was passed successfully; false if an error occured. 
   *  @access public
   */
  public function makeOrder($quotInfo, $getInfo = false) {
    $this->quotInfo = $quotInfo;
    $this->getInfo = $getInfo;
    if($quotInfo['reason']) {
      $quotInfo['envoi.raison'] = $this->shipReasons[$quotInfo['reason']];
      unset($quotInfo['reason']);
    }
    if($quotInfo['assurance.selected'] == '') {
      $quotInfo['assurance.selected'] = false;
    }
    $this->param = array_merge($this->param, $quotInfo);
    $this->setOptions(array('action' => '/api/v1/order'));
    $this->setPost();
		
    if($this->doSimpleRequest() && !$this->respError) {
			// The request is ok, we check the order reference
			$nodes = $this->xpath->query('/order/shipment');
			$reference = $nodes->item(0)->getElementsByTagName('reference')->item(0)->nodeValue;
			if(preg_match("/^[0-9]{10}[A-Z]{4}[0-9]{4}[A-Z]{2}$/", $reference)) {
        $this->order['ref'] = $reference;
        $this->order['date'] = date('Y-m-d H:i:s');
        if($getInfo) {
          $this->getOrderInfos();
        }
        return true;
      }
      return false;
    }
    else {
      return false;
    }
  }


  /** 
   *  Public getter of shippment reasons
   *  @access public
   *  @param array $translations Array with reasons' translations. You must translate by $this->shipReasons values, 
   *  not the keys.
   *  @return array Array with shippment reasons, may by used to pro forma generation. 
   */
  public function getReasons($translations) {
    $reasons = array();
    if(count($translations) == 0)
    {
      $translations = $this->shipReasons;
    }
    foreach($this->shipReasons as $r => $reason)
    {
      $reasons[$reason] = $translations[$r];
    }
    return $reasons;
  }


  /** 
   *  Method which allowes you to make double order (the same order in two directions : from shipper 
   *  to recipient and from recipient to shipper). It can be used by some stores for send a test product
   *  to customer and receive it back if the customer isn't satisfied. 
   *  @return boolean True if second order was passed successfully; false if an error occured. 
   */
  public function makeDoubleOrder($quotInfo = array(), $getInfo = false) {
    if(count($quotInfo) == 0) {
      $quotInfo = $this->quotInfo;
    }
    else {
      $quotInfo = $this->setNewQuotInfo($quotInfo);
    }
    $this->switchPeople();
    $this->makeOrder($quotInfo, $getInfo);
  }

  /** 
   *  Person switcher; it switchs shipper to recipient and recipient to shipper.  
   *  @return void
   */
  private function switchPeople() {
    $localParams = $this->param;
    $old = array('expediteur', 'destinataire', 'tmp_exp', 'tmp_dest');
    $new = array('tmp_exp', 'tmp_dest', 'destinataire', 'expediteur');
    foreach($localParams as $key => $value) {
      $this->param[str_replace($old, $new, $key)] = $value;
    }
  }

  /** 
   *  Setter for new request parameters. If a new parameter is defined, it overriddes the old one (for exemple new service,
   *  new hour disponibility).
   *  @return array Array containing new quotation informations.
   */
  private function setNewQuotInfo($quotInfo) {
    foreach((array)$this->quotInfo as $q => $info) {
      if(array_key_exists($q, $quotInfo)) {
        $this->quotInfo[$q] = $quotInfo[$q];
      }
    }
    foreach($quotInfo as $q => $info) {
      if(!array_key_exists($q, (array)$this->quotInfo)) {
        $this->quotInfo[$q] = $quotInfo[$q];
      }
    }
    return $this->quotInfo;
  }

  /** 
   *  Method which removes old quotation parameters.
   *  @return void
   */
  public function unsetParams($quotInfo) {
    foreach($quotInfo as $info) {
      unset($this->quotInfo[$info]);
      unset($this->param[$info]);
    }
  }

}
?>