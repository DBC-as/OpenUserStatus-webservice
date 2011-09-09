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
  private function _set(&$target, $name, $source, $ns="http://oss.dbc.dk/ns/openuserstatus") {
    if (!isset($source)) return;
    $target->$name->_value = $source;
    $target->$name->_namespace = $ns;
  }
  

/** \brief _add
*
*/
  private function _add(&$target, $name, $source, $ns="http://oss.dbc.dk/ns/openuserstatus") {
    if (empty($source)) return;
    $element->_value = $source;
    $element->_namespace = $ns;
    $target->{$name}[] = $element;
  }
  

/** \brief _bib_info
 *
 * @param integer $bibno Biblioteks nummeret
 * 
 * @return Bib info
 *
 */
  private function _bib_info($bibno) {
    $bib_info = new bibdk_info($this->config->get_value("oci_credentials", "setup"),$this->config->get_section("cache"));
    $this->watch->start("bib_info");
    $ret = $bib_info->get_bib_info($bibno);    
    $this->watch->stop("bib_info");
    return $ret;
  }


/** \brief _bib_navn
 *
 * @param array $favorit Array:
 * 
 * @return Bib navn
 *
 */
  private function _bib_navn($favorit) {
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
  private function _build_error($name, $text) {
    $resp_name = $name . "Response";
    $error_name = $name . "Error";
    self::_set($error, $error_name, $text);
    self::_set($ret, $resp_name, $error);
    return $ret;
  }


  private function _pack_agency($agency, $subdivision='') {
    list($p1, $p2) = explode('-', $agency, 2);
    $bib_id = isset($p2) ? $p2 : $p1;
    if (empty($subdivision)) {
      return 'DK-' . $bib_id;
    } else {
      return 'DK-' . $bib_id . '_' . $subdivision;
    }
  }


  private function _unpack_agency($id) {
    if (preg_match("/^DK-(\d+)(_(.*))?$/i", trim($id), $matches)) {
      return array($matches[1], $matches[3]);
    } else {
      return array($id);
    }
  }


  private function _parse_date_time($date) {
    if (empty($date)) return null;
    if (preg_match('/^(\d\d\d\d)$/', trim($date))) {
      $dateTime = new DateTime($date . '-01-01');  // Hvis kun årstal er angivet, sæt dato til 1. januar det samme år
    } else {
      $dateTime = new DateTime($date);
    }
  return $dateTime->format(DateTime::W3C);
  }


  private function lookup_loan($params,$fav_info,$itemid) {
    $ncip_lookup_item = new ncip();
    $lookup_item = $ncip_lookup_item->request($fav_info["ncip_lookup_user_address"],
					      array("Ncip" => "LookupItem",
						    "FromAgencyId" => "DK-190101",
						    "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
						    "ToAgencyId" => $params->agencyId->_value,
						    "UniqueItemId" => $itemid
						    ));
    return $lookup_item;
  }


  private function lookup_request($params,$fav_info,$itemid) {
    $ncip_lookup_request = new ncip();
    $lookup_request = $ncip_lookup_request->request(
      $fav_info["ncip_lookup_user_address"],
      array(
        "Ncip" => "LookupRequest",
        "FromAgencyId" => "DK-190101",
        "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
        "ToAgencyId" => $params->agencyId->_value,
        "UniqueRequestId" => $itemid,
      )
    );
    return $lookup_request;					    
  }


  private function lookup_user($params,$fav_info) {
    $ncip_lookup_user = new ncip();
    $lookup_user = $ncip_lookup_user->request(
      $fav_info["ncip_lookup_user_address"],
      array(
        "Ncip" => "LookupUser",
        "FromAgencyId" => "DK-190101",
        "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
        "ToAgencyId" => $params->agencyId->_value,
        "UserId" => $params->userId->_value,
        "UserPIN" => $params->userPincode->_value,
      )
    );
    return $lookup_user;    
  }


  private function set_order($request) {
    $res = self::set_bibliographic_info($request);
    return self::set_basic_order($res, $request);
  }


  private function set_basic_order(&$result, $request)
  {
    self::_set($result, "orderDate", self::_parse_date_time($request["DatePlaced"]));
    self::_set($result, "orderId", $request["UniqueRequestId"]["RequestIdentifierValue"]);
    self::_set($result, "orderStatus", ucfirst(strtolower($request["RequestStatusType"])));
    self::_set($result, "orderType", $request["RequestType"]);
    self::_set($result, "dateAvailable", self::_parse_date_time($request["DateAvailable"]));
    self::_set($result, "holdQueuePosition", $request["HoldQueuePosition"]);
    self::_set($result, "needBeforeDate", self::_parse_date_time($request["NeedBeforeDate"]));
    self::_set($result, "orderExpiryDate", self::_parse_date_time($request["NeedBeforeDate"]));
    self::_set($result, "pickUpDate", self::_parse_date_time($request["PickupDate"]));
    self::_set($result, "pickUpExpiryDate", self::_parse_date_time($request["PickupExpiryDate"]));
    self::_set($result, "pickUpId", $request["LocationWithinBuilding"]);
    list($agency, $subdivision) = self::_unpack_agency($request['FromAgencyId']);
    self::_set($result, "pickUpAgency",  self::_pack_agency($agency));
    self::_set($result, "pickUpAgencySubdivision", $subdivision);
    self::_set($result, "reminderLevel", $request["ReminderLevel"]);
    return $result;
  }


  private function set_loan($item) {
    $loan = self::set_bibliographic_info($item);
    return self::set_basic_loan($loan, $item);
  }


  private function set_basic_loan(&$loan, $item) {
    self::_set($loan, "dateDue", self::_parse_date_time($item["DateDue"]));
    self::_set($loan, "loanId", $item["UniqueItemId"]["ItemIdentifierValue"]);
    self::_set($loan, "loanRecallDate", self::_parse_date_time($item["DateRecalled"]));
    self::_set($loan, "reminderLevel", $item["ReminderLevel"]);
    return $loan;
  }


  private function set_bibliographic_info($info) {
    unset($result);
    self::_set($result, "author", $info["Author"]);
    self::_set($result, "authorOfComponent", $info["AuthorOfComponent"]);
    self::_set($result, "bibliographicItemId", $info["BibliographicItemIdentifier"]);
    self::_set($result, "bibliographicRecordId", $info["BibliographicRecordIdentifier"]);
    self::_set($result, "componentId", $info["ComponentIdentifier"]);
    self::_set($result, "edition", $info["Edition"]);
    self::_set($result, "pagination", $info["Pagination"]);
    self::_set($result, "placeOfPublication", $info["PlaceOfPublication"]);
    self::_set($result, "publicationDateOfComponent", $info["PublicationDateOfComponent"]);
    self::_set($result, "publisher", $info["Publisher"]);
    self::_set($result, "seriesTitleNumber", $info["SeriesTitleNumber"]);
    self::_set($result, "titleOfComponent", $info["TitleOfComponent"]);
    self::_set($result, "bibliographicLevel", $info["BibliographicLevel"]);
    self::_set($result, "sponsoringBody", $info["SponsoringBody"]);
    self::_set($result, "electronicDataFormatType", $info["ElectronicDataFormatType"]);
    self::_set($result, "language", $info["Language"]);
    self::_set($result, "mediumType", $info["MediumType"]);
    self::_set($result, "publicationDate", $info["PublicationDate"]);
    self::_set($result, "title", $info["Title"]);
    return $result;
  }


  private function set_fiscalAccount($fiscal) {
    unset($result);
    self::_set($result, "fiscalTransactionAmount", $fiscal["MonetaryValue"]);
    self::_set($result, "fiscalTransactionCurrency", $fiscal["CurrencyCode"]);
    self::_set($result, "fiscalTransactionDate", self::_parse_date_time($fiscal["AccrualDate"]));
    self::_set($result, "fiscalTransactionType", $fiscal["FiscalTransactionType"]);
    self::_set($result, "bibliographicRecordId", $fiscal["BibliographicRecordId"]);
    self::_set($result, "author", $fiscal["Author"]);
    self::_set($result, "title", $fiscal["Title"]);
    return $result;
  }



//==============================================================================
//
// Public Methods
//
//==============================================================================


 /** \brief renewLoan - 
  *
  *
  */

  function renewLoan($param) {
  if (!$this->aaa->has_right("openuserstatus", 500)) return self::_build_error("renewLoan", "authentication_error");
    if (!isset($param->userId)) return self::_build_error("renewLoan", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return self::_build_error("renewLoan", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    if (!isset($param->loanId)) return self::_build_error("renewLoan", "Element rule violated");
    if (is_array($param->loanId))
      foreach ($param->loanId as $l) $loanIds[] = $l->_value;
    else
      $loanIds[] = $param->loanId->_value;

    $fav_info = self::_bib_info($bib_id);
    if (strtoupper($fav_info["ncip_renew"]) !== "J") return self::_build_error("renewLoan", "Service unavailable");

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
      self::_set($loanStatus, "loanId", $loanId);
      if (isset($renew["Problem"])) {
        self::_set($loanStatus, 'renewLoanError', $renew["Problem"]["Type"]);
      } else {
        self::_set($loanStatus, 'dateDue', self::_parse_date_time($renew["DateDue"]));
        self::_set($loanStatus, 'dateOfExpectedReply', self::_parse_date_time($renew["DateOfExpectedReply"]));
      }
      self::_add($response, 'renewLoanStatus', $loanStatus);
    }

    self::_set($ret, 'renewLoanResponse', $response);
    return $ret;
  }

//==============================================================================

 /** \brief cancelOrder - 
  *
  *
  */

  function cancelOrder($param) {
  if (!$this->aaa->has_right("openuserstatus", 500)) return self::_build_error("cancelOrder", "authentication_error");
    if (!isset($param->userId)) return self::_build_error("cancelOrder", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return self::_build_error("cancelOrder", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    if (!isset($param->cancelOrder)) return self::_build_error("cancelOrder", "Element rule violated");
    unset($cancelOrders);
    if (is_array($param->cancelOrder))
      foreach ($param->cancelOrder as $cOrder) $cancelOrders[] = array("orderId" => $cOrder->_value->orderId->_value, "orderType" => $cOrder->_value->orderType->_value );
    else
      $cancelOrders[] = array("orderId" => $param->cancelOrder->_value->orderId->_value, "orderType" => $param->cancelOrder->_value->orderType->_value );

    $fav_info = self::_bib_info($bib_id);
    if (strtoupper($fav_info["ncip_cancel"]) !== "J") return self::_build_error("cancelOrder", "Service unavailable");

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
      self::_set($orderStatus, 'orderId', $cancelOrder["orderId"]);
      if (isset($cancel["Problem"]))
        self::_set($orderStatus, 'cancelOrderError', $cancel["Problem"]["Type"]);
      else
        self::_set($orderStatus, 'orderCancelled', '');
      self::_add($response, 'cancelOrderStatus', $orderStatus);
    }
    self::_set($ret, 'cancelOrderResponse', $response);
    return $ret;
  }

//==============================================================================

 /** \brief updateOrder - 
  *
  *
  */

  function updateOrder($param) {
  if (!$this->aaa->has_right("openuserstatus", 500)) return self::_build_error("updateOrder", "authentication_error");
    if (!isset($param->userId)) return self::_build_error("updateOrder", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return self::_build_error("updateOrder", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);
    if (!isset($param->updateOrder)) return self::_build_error("updateOrder", "Element rule violated");
    unset($updateOrders);
    if (is_array($param->updateOrder)) {
      $orders = $param->updateOrder;
    } else {
      $orders[] = $param->updateOrder;
    }
    foreach ($orders as $cOrder) {
      $updateOrders[] = array("orderId" => $cOrder->_value->orderId->_value,
                              "pickUpAgency" => $cOrder->_value->pickUpAgency->_value,
                              "pickUpAgencySubdivision" => $cOrder->_value->pickUpAgencySubdivision->_value,
                             );
    }

    $fav_info = self::_bib_info($bib_id);
    if (strtoupper($fav_info["ncip_update_request"]) !== "J") return self::_build_error("updateOrder", "Service unavailable");

    foreach ($updateOrders as $updateOrder) {
      $ncip_update = new ncip();
      $update = $ncip_update->request($fav_info["ncip_update_request_address"],
                                      array("Ncip" => "UpdateRequestItem",
                                            "FromAgencyId" => "DK-190101",
                                            "FromAgencyAuthentication" => $fav_info["ncip_update_request_password"],
                                            "ToAgencyId" => self::_pack_agency($updateOrder['pickUpAgency'], $updateOrder['pickUpAgencySubdivision']),
                                            "UniqueUserId" => array("UserIdentifierValue" => $userId, "UniqueAgencyId" => $agencyId),
                                            "UniqueRequestId" => array("RequestIdentifierValue" => $updateOrder["orderId"], "UniqueAgencyId" => $agencyId),
                                           )
                                     );
      unset($orderStatus);
      self::_set($orderStatus, 'orderId', $updateOrder["orderId"]);
      if (isset($update["Problem"]))
        self::_set($orderStatus, 'updateOrderError', $update["Problem"]["Type"]);
      else
        self::_set($orderStatus, 'orderUpdated', '');
      self::_add($response, 'updateOrderStatus', $orderStatus);
    }
    self::_set($ret, 'updateOrderResponse', $response);
    return $ret;
  }

//==============================================================================

 /** \brief getUserStatus - 
  *
  */

  function getUserStatus($param) {
    if (!$this->aaa->has_right("openuserstatus", 500)) return self::_build_error("getUserStatus", "authentication_error");
    if (!isset($param->userId)) return self::_build_error("getUserStatus", "Element rule violated");
    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    if (!isset($param->agencyId)) return self::_build_error("getUserStatus", "Element rule violated");
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);

    $fav_info = self::_bib_info($bib_id);

    $loan_items = array();
    if (strtoupper($fav_info["ncip_lookup_user"]) !== "J") return self::_build_error("getUserStatus", "Service unavailable");
    
    $lookup_user = self::lookup_user($param,$fav_info);
    if (isset($lookup_user["Problem"])) return self::_build_error("getUserStatus", $lookup_user["Problem"]["Type"]);

    $lookup_items = array();
    if (is_array($lookup_user["LoanedItem"]))
      foreach ($lookup_user["LoanedItem"] as $loaned_item) {
        $lookup_item = self::lookup_loan($param,$fav_info,$loaned_item["UniqueItemId"]);
        if (!isset($lookup_item["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
          $lookup_item = array_merge($lookup_item, $loaned_item);
          $lookup_items[] = $lookup_item;
        }
      }
    $lookup_requests = array();
    if (is_array($lookup_user["RequestedItem"]))
      foreach ($lookup_user["RequestedItem"] as $requested_item) {
        $lookup_request = self::lookup_request($param,$fav_info,$requested_item["UniqueRequestId"]);
        if (!isset($lookup_request["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
          $lookup_request = array_merge($requested_item, $lookup_request);
          $lookup_requests[] = $lookup_request;  
        }
      }
    // Set output object for xml data creation
    self::_set($xresponse, 'userId', $userId);
    if (is_array($lookup_items)) foreach($lookup_items as $item) {
      self::_add($xloans, 'loan', self::set_loan($item));
    }
    self::_set($xloans, 'loansCount', count($lookup_items));
    self::_set($xuserStatus, 'loanedItems', $xloans);
    if (is_array($lookup_requests)) foreach($lookup_requests as $request) {
      self::_add($xorders, 'order', self::set_order($request));
    }
    self::_set($xorders, 'ordersCount', count($lookup_requests));
    self::_set($xuserStatus, 'orderedItems', $xorders);
    if (is_array($lookup_user['UserFiscalAccount'])) {
      foreach($lookup_user['UserFiscalAccount'] as $i => $userFiscal) {
        if (is_numeric($i)) {
          self::_add($xfiscalAccount, 'fiscalTransaction', self::set_fiscalAccount($userFiscal));
        }
      }
    }
    self::_set($xfiscalAccount, 'totalAmount', $lookup_user['UserFiscalAccount']['AccountBalanceValue']);
    self::_set($xfiscalAccount, 'totalAmountCurrency', $lookup_user['UserFiscalAccount']['AccountBalanceCurrency']);
    self::_set($xuserStatus, 'fiscalAccount', $xfiscalAccount);

    self::_set($xresponse, 'userStatus', $xuserStatus);
    self::_set($xret, 'getUserStatusResponse', $xresponse);
    return $xret;
  }



}

/* 
 * MAIN 
 */

$ws=new openUserStatus('openuserstatus.ini');
$ws->handle_request();

?>
