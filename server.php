<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
*/

set_include_path(get_include_path() . PATH_SEPARATOR . "OLS_class_lib" . PATH_SEPARATOR . "DBC_class_lib");

require_once("webServiceServer_class.php");
require_once("oci_class.php");
require_once("ncip_class.php");
require_once("bibdk_info_class.php");

define("HTTP_PROXY", "phobos.dbc.dk:3128");


class openUserStatus extends webServiceServer {


/** \brief _set
*
*/
  private function _set(&$target, $name, $source) 
  {
    if (empty($source)) return;
    $target->$name->_value = $source;
  }
  

/** \brief _bib_info
 *
 * @param integer $bibno Biblioteks nummeret
 * 
 * @return Bib info
 *
 */
  private function _bib_info($bibno) 
  {
    $bib_info = new bibdk_info($this->config->get_value("oci_credentials", "setup"),$this->config->get_section("cache"));
    $this->watch->start("bib_info");
    $ret = $bib_info->get_bib_info($bibno);    
    $this->watch->stop("bib_info");
    
    //  print_r($ret);
    // exit;

    return $ret;
  }


/** \brief _bib_navn
 *
 * @param array $favorit Array:
 * 
 * @return Bib navn
 *
 */
  private function _bib_navn($favorit) 
  {
    if (!empty($favorit["navn_k"])) {
      return $favorit["navn_k"];
    } else {
      return $favorit["navn"];
    }
  }


/** \brief _build_error
 *
 * @param string Name
 * 
 * @return Object error
 *
 */
  private function _build_error($name, $text) 
  {
    $resp_name = $name . "Response";
    //  $error_name = $name . "Error";
    $error_name =  "Error";
    $ret->$resp_name->_value->$error_name->_value = $text;
    return $ret;
  }


//==============================================================================


 /** \brief renewLoan - 
  *
  * Request:
  * - serviceRequester
  * - userId
  * - userPincode
  * - agencyId
  * - loanId
  * 
  * Response:
  * - renewLoanStatus
  * - - loanId
  * - - dateDue
  * - - dateOfExpectedReply
  * - - renewLoanError
  * - renewLoanError
  *
  */

  // pjo this function handles updates for loans (Ncip=>RenewItem)
  function renewLoan($param) 
  {
//    if (!isset($param->serviceRequester)) return $this->_build_error("renewLoan", "Element rule violated");
//    $servicerRequester = $param->serviceRequester->_value;
  
//pjo 05-11-10 commented out aaa-line for testing. uncomment for production
//  if (!$this->aaa->has_right("openuserstatus", 500)) return $this->_build_error("renewLoan", "authentication_error");
    if (!isset($param->userId)) return $this->_build_error("renewLoan", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return $this->_build_error("renewLoan", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    if (!isset($param->loanId)) return $this->_build_error("renewLoan", "Element rule violated");
    if (is_array($param->loanId))
      foreach ($param->loanId as $l) $loanIds[] = $l->_value;
    else
      $loanIds[] = $param->loanId->_value;

    $fav_info = $this->_bib_info($bib_id);
    if (strtoupper($fav_info["ncip_renew"]) !== "J") return $this->_build_error("renewLoan", "Service unavailable");

    foreach ($loanIds as $loanId) {
      $ncip_renew = new ncip();
      $renew = $ncip_renew->request($fav_info["ncip_renew_address"],
                                    array("Ncip" => "RenewItem",
                                          "FromAgencyId" => "DK-190101",
                                          "FromAgencyAuthentication" => $fav_info["ncip_renew_password"],
                                          "ToAgencyId" => $agencyId,
                                          "UniqueUserId" => array("UserIdentifierValue" => $userId, "UniqueAgencyId" => $agencyId),
                                          "UniqueItemId" => array("ItemIdentifierValue" => $loanId, "UniqueAgencyId" => $agencyId ) ) );
      unset($loanStatus);
      $loanStatus->loanId->_value = $loanId;
      if (isset($renew["Problem"])) {
        $loanStatus->renewLoanError->_value = $renew["Problem"]["Type"];
      } else {
        if (isset($renew["DateDue"])) $loanStatus->dateDue->_value = $renew["DateDue"];
        if (isset($renew["DateOfExpectedReply"])) $loanStatus->dateOfExpectedReply->_value = $renew["DateOfExpectedReply"];
      }
      $response->renewLoanStatus[]->_value = $loanStatus;
    }

    $ret->renewLoanResponse->_value = $response;
    return $ret;
  }

 /** \brief cancelOrder - 
  *
  * Request:
  * - serviceRequester
  * - userId
  * - userPincode
  * - agencyId
  * - cancelOrder
  *
  * Response:
  * - cancelOrderStatus
  * - - orderId
  * - - orderCancelled
  * - - cancelOrderError
  * - cancelOrderError
  *
  */

  // pjo this function handles deletion (from bibdk slet markerede) Ncip=>CancelRequestItem
  function cancelOrder($param) 
  {
//    if (!isset($param->serviceRequester)) return $this->_build_error("cancelOrder", "Element rule violated");
//    $servicerRequester = $param->serviceRequester->_value;
//pjo 05-11-10 commented out aaa-line for testing. uncomment for production
//  if (!$this->aaa->has_right("openuserstatus", 500)) return $this->_build_error("cancelOrder", "authentication_error");
    if (!isset($param->userId)) return $this->_build_error("cancelOrder", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return $this->_build_error("cancelOrder", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    if (!isset($param->cancelOrder)) return $this->_build_error("cancelOrder", "Element rule violated");
    unset($cancelOrders);
    if (is_array($param->cancelOrder))
      foreach ($param->cancelOrder as $cOrder) $cancelOrders[] = array("orderId" => $cOrder->_value->orderId->_value, "orderType" => $cOrder->_value->orderType->_value );
    else
      $cancelOrders[] = array("orderId" => $param->cancelOrder->_value->orderId->_value, "orderType" => $param->cancelOrder->_value->orderType->_value );

    $fav_info = $this->_bib_info($bib_id);
    if (strtoupper($fav_info["ncip_cancel"]) !== "J") return $this->_build_error("cancelOrder", "Service unavailable");

    foreach ($cancelOrders as $cancelOrder) {
      $ncip_cancel = new ncip();
      $cancel = $ncip_cancel->request($fav_info["ncip_cancel_address"],
                                      array("Ncip" => "CancelRequestItem",
                                            "FromAgencyId" => "DK-190101",
                                            "FromAgencyAuthentication" => $fav_info["ncip_cancel_password"],
                                            "ToAgencyId" => $agencyId,
                                            "UniqueUserId" => array("UserIdentifierValue" => $userId, "UniqueAgencyId" => $agencyId),
                                            "UniqueRequestId" => array("RequestIdentifierValue" => $cancelOrder["orderId"], "UniqueAgencyId" => $agencyId),
                                            "RequestType" => $cancelOrder["orderType"] ) );
      unset($orderStatus);
      $orderStatus->orderId->_value = $cancelOrder["orderId"];
      if (isset($cancel["Problem"]))
        $orderStatus->cancelOrderError->_value = $cancel["Problem"]["Type"];
      else
        $orderStatus->orderCancelled->_value = "";
      $response->cancelOrderStatus[]->_value = $orderStatus;
    }
    $ret->cancelOrderResponse->_value = $response;
    return $ret;
  }

 /** \brief getUserRequest - 
  *
  * Request:
  * - serviceRequester
  * - userId
  * - userPincode
  * - agencyId
  *
  * Response:
  * - userStatus
  * - - loanedItems
  * - - - loansCount
  * - - - loans
  * - - - - loanId
  * - - - - author
  * - - - - authorOfComponent
  * - - - - bibliographicItemId
  * - - - - bibliographicRecordId
  * - - - - componentId
  * - - - - edition
  * - - - - pagination
  * - - - - placeOfPublication
  * - - - - publicationDateOfComponent
  * - - - - publisher
  * - - - - seriesTitleNumber
  * - - - - titleOfComponent
  * - - - - bibliographicLevel
  * - - - - sponsoringBody
  * - - - - electronicDataFormatType
  * - - - - language
  * - - - - mediumType
  * - - - - publicationDate
  * - - - - title
  * - - - - dateDue
  * - - - - reminderLevel
  * - - - - loanRecallDate
  * - - orderedItems
  * - - - ordersCount
  * - - - order
  * - - - - orderId
  * - - - - author
  * - - - - authorOfComponent
  * - - - - bibliographicItemId
  * - - - - bibliographicRecordId
  * - - - - componentId
  * - - - - edition
  * - - - - pagination
  * - - - - placeOfPublication
  * - - - - publicationDateOfComponent
  * - - - - publisher
  * - - - - seriesTitleNumber
  * - - - - titleOfComponent
  * - - - - bibliographicLevel
  * - - - - sponsoringBody
  * - - - - electronicDataFormatType
  * - - - - language
  * - - - - mediumType
  * - - - - publicationDate
  * - - - - title
  * - - - - orderStatus
  * - - - - orderDate
  * - - - - orderType
  * - - - - dateAvailable
  * - - - - holdQueuePosition
  * - - - - needBeforeDate
  * - - - - orderExpiryDate
  * - - - - pickupDate
  * - - - - pickupExpiryDate
  * - - - - reminderLevel
  */

  // pjo this function handles userinfo (initialization for bibdk) Ncip=>LookupUser. 
  // it also handles request for individual items(loans) Ncip=>LookupItem
  // it also handles 
  function getUserStatus($param) 
  {
//    if (!isset($param->serviceRequester)) return $this->_build_error("getUserStatus", "Element rule violated");
//    $servicerRequester = $param->serviceRequester->_value;
//pjo 05-11-10 commented out aaa-line for testing. uncomment for production
//    if (!$this->aaa->has_right("openuserstatus", 500)) return $this->_build_error("getUserStatus", "authentication_error");
    if (!isset($param->userId)) return $this->_build_error("getUserStatus", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return $this->_build_error("getUserStatus", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);

    $fav_info = $this->_bib_info($bib_id);

//print_r($fav_info);
//exit;

    $loan_items = array();
    if (strtoupper($fav_info["ncip_lookup_user"]) !== "J") return $this->_build_error("getUserStatus", "Service unavailable");
    
    $lookup_user = $this->lookup_user($param,$fav_info);
    if (isset($lookup_user["Problem"])) return $this->_build_error("getUserStatus", $lookup_user["Problem"]["Type"]);

    $lookup_items = array();
    if (is_array($lookup_user["LoanedItem"]))
      foreach ($lookup_user["LoanedItem"] as $loaned_item) {
	$lookup_item = $this->lookup_loan($param,$fav_info,$loaned_item["UniqueItemId"]);

        if (!isset($lookup_item["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
          $lookup_item = array_merge($lookup_item, $loaned_item);
          $lookup_items[] = $lookup_item;
        }
      }
    $lookup_requests = array();
    if (is_array($lookup_user["RequestedItem"]))
      foreach ($lookup_user["RequestedItem"] as $requested_item) {
	
	$lookup_request = $this->lookup_request($param,$fav_info,$requested_item["UniqueRequestId"]);

        if (!isset($lookup_request["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
          $lookup_request = array_merge($requested_item, $lookup_request);
          $lookup_requests[] = $lookup_request;  
        }
      }

    $response->userId->_value = $userId;
    $response->userStatus->_value->loanedItems->_value->loansCount->_value = count($lookup_items);
    if (is_array($lookup_items))
      foreach($lookup_items as $item) {
        unset($loan);
        $loan = $this->set_loan($item);
        $response->userStatus->_value->loanedItems->_value->loan[]->_value = $loan;
      }
    $response->userStatus->_value->orderedItems->_value->ordersCount->_value = count($lookup_requests);
    if (is_array($lookup_requests))
      foreach($lookup_requests as $request) {
        unset($res);
        $res=$this->set_order($request);
        $response->userStatus->_value->orderedItems->_value->order[]->_value = $res;
      }
    $ret->getUserStatusResponse->_value = $response;
    return $ret;
  }

  function getUserLoan($param)
  {
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    $fav_info = $this->_bib_info($bib_id);

    $itemid=array("ItemIdentifierValue"=>$param->loanId->_value,"UniqueAgencyId"=>$agencyId);

    $this->watch->start("lookup_loan");
    $loan = $this->lookup_loan($param,$fav_info,$itemid);
    $this->watch->stop("lookup_loan");

    if(isset($loan["Problem"]))
      return $this->_build_error("getUserLoan", $loan["Problem"]["Type"]);

    $loan = $this->set_loan($loan);

    $ret->getUserLoanResponse->_value= $loan;

    return $ret;

    // print_r($loan);
    //exit;
  }

  function getUserOrder($param)
  {
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    $fav_info = $this->_bib_info($bib_id);

    $itemid=array("RequestIdentifierValue"=>$param->orderId->_value,"UniqueAgencyId"=>$agencyId);

    $this->watch->start("lookup_order");
    $order = $this->lookup_request($param,$fav_info,$itemid);
    $this->watch->stop("lookup_order");

    if(isset($order["Problem"]))
      return $this->_build_error("getUserOrder", $order["Problem"]["Type"]);

    $order = $this->set_order($order);

    $ret->getUserOrderResponse->_value= $order;

    return $ret;

    //print_r($param);
    //exit;
  }

  function getUser($param)
  {
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    $fav_info = $this->_bib_info($bib_id);
   

    $this->watch->start("lookup_user");
    $user = $this->lookup_user($param,$fav_info);
    $this->watch->stop("lookup_user");
  
    if(isset($user["Problem"]))
      return $this->_build_error("getUser", $user["Problem"]["Type"]);

    $response->userId->_value = $param->userId->_value;
    $response->userPincode->_value = $param->userPincode->_value;
    $response->loansCount->_value = count($user["LoanedItem"]);
    if( is_array($user["LoanedItem"]) )
      foreach ($user["LoanedItem"] as $item)
	{
	  unset($loan);
	  $loan = $this->set_basic_loan($item);
	  $response->basicLoans->_value->basicLoan[]->_value = $loan;
	}

    $response->ordersCount->_value = count($user["RequestedItem"]);     
    if( is_array($user["RequestedItem"] ) )
      foreach( $user["RequestedItem"] as $item )
	{
	  unset($order);
	  $order = $this->set_basic_order($item);
	  $response->basicOrders->_value->basicOrder[]->_value = $order;
	}

    verbose::log(STAT,'bibno:'.$agencyId.' userid:'.$param->userId->_value);
       
    $ret->getUserResponse->_value = $response;

    return $ret;
  }
  

   // pjo 11-11-10 
  private function lookup_loan($params,$fav_info,$itemid)
  {
    $ncip_lookup_item = new ncip();
    $lookup_item = $ncip_lookup_item->request($fav_info["ncip_lookup_user_address"],
					      array("Ncip" => "LookupItem",
						    "FromAgencyId" => "DK-190101",
						    "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
						    "ToAgencyId" => $params->agencyId->_value,//$agencyId,
						    "UniqueItemId" => $itemid//$loaned_item["UniqueItemId"] 
						    ));
    return $lookup_item;
  }

  // pjo 11-11-10 
  private function lookup_request($params,$fav_info,$itemid)
  {
    $ncip_lookup_request = new ncip();
    $lookup_request = $ncip_lookup_request->request($fav_info["ncip_lookup_user_address"],
						    array("Ncip" => "LookupRequest",
							  "FromAgencyId" => "DK-190101",
							  "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
							  "ToAgencyId" => $params->agencyId->_value,//$agencyId,
							  "UniqueRequestId" => $itemid )
	
						    );
    return $lookup_request;					    
    
  }

  private function lookup_user($params,$fav_info)
  {
    $ncip_lookup_user = new ncip();
    $lookup_user = $ncip_lookup_user->request($fav_info["ncip_lookup_user_address"],
                                              array("Ncip" => "LookupUser",
                                                    "FromAgencyId" => "DK-190101",
                                                    "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
                                                    "ToAgencyId" => $params->agencyId->_value,
                                                    "UserId" => $params->userId->_value,
                                                    "UserPIN" => $params->userPincode->_value ) );
    
     
    return $lookup_user;    
  }

   // pjo 11-11-10 
  private function set_order($request)
  {
    $res = $this->set_basic_order($request);
    //$res->orderId->_value = $request["UniqueRequestId"]["RequestIdentifierValue"];
    $this->_set($res, "author", $request["Author"]);
    $this->_set($res, "authorOfComponent", $request["AuthorOfComponent"]);
    $this->_set($res, "bibliographicItemId", $request["BibliographicItemId"]);
    $this->_set($res, "bibliographicRecordId", $request["BibliographicRecordId"]);
    $this->_set($res, "componentId", $request["ComponentId"]);
    $this->_set($res, "edition", $request["Edition"]);
    $this->_set($res, "pagination", $request["Pagination"]);
    $this->_set($res, "placeOfPublication", $request["PlaceOfPublication"]);
    $this->_set($res, "publicationDateOfComponent", $request["PublicationDateOfComponent"]);
    $this->_set($res, "publisher", $request["Publisher"]);
    $this->_set($res, "seriesTitleNumber", $request["SeriesTitleNumber"]);
    $this->_set($res, "titleOfComponent", $request["TitleOfComponent"]);
    $this->_set($res, "bibliographicLevel", $request["BibliographicLevel"]);
    $this->_set($res, "sponsoringBody", $request["SponsoringBody"]);
    $this->_set($res, "electronicDataFormatType", $request["ElectronicDataFormatType"]);
    $this->_set($res, "language", $request["Language"]);
    $this->_set($res, "mediumType", $request["MediumType"]);
    $this->_set($res, "publicationDate", $request["PublicationDate"]);
    $this->_set($res, "title", $request["Title"]);


    // rest are basic order
    /* $this->_set($res, "orderStatus", $request["RequestStatusType"]);
    $this->_set($res, "orderDate", $request["DatePlaced"]);
    $this->_set($res, "orderType", $request["RequestType"]);
    $this->_set($res, "dateAvailable", $request["DateAvailable"]);
    $this->_set($res, "holdQueuePosition", $request["HoldQueuePosition"]);
    $this->_set($res, "needBeforeDate", $request["NeedBeforeDate"]);
    $this->_set($res, "orderExpiryDate", $request["HoldPickupDate"]);
    $this->_set($res, "pickupDate", $request["PickupDate"]);
    $this->_set($res, "pickupExpiryDate", $request["PickupExpiryDate"]);
    $this->_set($res, "reminderLevel", $request["ReminderLevel"]);*/

    return $res;
  }

  private function set_basic_order($request)
  {
    $res->orderId->_value = $request["UniqueRequestId"]["RequestIdentifierValue"];
    $res->agencyId->_value = $request["UniqueRequestId"]["UniqueAgencyId"];

    $this->_set($res, "orderStatus", $request["RequestStatusType"]);
    $this->_set($res, "orderDate", $request["DatePlaced"]);
    $this->_set($res, "orderType", $request["RequestType"]);
    $this->_set($res, "dateAvailable", $request["DateAvailable"]);
    $this->_set($res, "holdQueuePosition", $request["HoldQueuePosition"]);
    $this->_set($res, "needBeforeDate", $request["NeedBeforeDate"]);
    $this->_set($res, "orderExpiryDate", $request["HoldPickupDate"]);
    $this->_set($res, "pickupDate", $request["PickupDate"]);
    $this->_set($res, "pickupExpiryDate", $request["PickupExpiryDate"]);
    $this->_set($res, "reminderLevel", $request["ReminderLevel"]);

    return $res;
  }

  private function set_basic_loan($item)
  {
    $loan->loanId->_value = $item["UniqueItemId"]["ItemIdentifierValue"];
    $loan->agencyId->_value = $item["UniqueItemId"]["UniqueAgencyId"];

    $this->_set($loan, "dateDue", $item["DateDue"]);
    $this->_set($loan, "reminderLevel", $item["ReminderLevel"]);
    $this->_set($loan, "loanRecallDate", $item["DateRecalled"]);

    return $loan;
  }

   // pjo 11-11-10 
  private function set_loan($item)
  {
    $loan = $this->set_basic_loan($item);
    //$loan->loanId->_value = $item["UniqueItemId"]["ItemIdentifierValue"];
    $this->_set($loan, "author", $item["Author"]);
    $this->_set($loan, "authorOfComponent", $item["AuthorOfComponent"]);
    $this->_set($loan, "bibliographicItemId", $item["BibliographicItemId"]);
    $this->_set($loan, "bibliographicRecordId", $item["BibliographicRecordId"]);
    $this->_set($loan, "componentId", $item["ComponentId"]);
    $this->_set($loan, "edition", $item["Edition"]);
    $this->_set($loan, "pagination", $item["Pagination"]);
    $this->_set($loan, "placeOfPublication", $item["PlaceOfPublication"]);
    $this->_set($loan, "publicationDateOfComponent", $item["PublicationDateOfComponent"]);
    $this->_set($loan, "publisher", $item["Publisher"]);
    $this->_set($loan, "seriesTitleNumber", $item["SeriesTitleNumber"]);
    $this->_set($loan, "titleOfComponent", $item["TitleOfComponent"]);
    $this->_set($loan, "bibliographicLevel", $item["BibliographicLevel"]);
    $this->_set($loan, "sponsoringBody", $item["SponsoringBody"]);
    $this->_set($loan, "electronicDataFormatType", $item["ElectronicDataFormatType"]);
    $this->_set($loan, "language", $item["Language"]);
    $this->_set($loan, "mediumType", $item["MediumType"]);
    $this->_set($loan, "publicationDate", $item["PublicationDate"]);
    $this->_set($loan, "title", $item["Title"]);

    // rest are basic_loan
    /* $this->_set($loan, "dateDue", $item["DateDue"]);
    $this->_set($loan, "reminderLevel", $item["ReminderLevel"]);
    $this->_set($loan, "loanRecallDate", $item["DateRecalled"]);*/

    return $loan;
  }
}

/* 
 * MAIN 
 */

$ws=new openUserStatus('openuserstatus.ini');
$ws->handle_request();

?>
