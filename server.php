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

class openUserStatus extends webServiceServer {

/*

<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:open="http://oss.dbc.dk/ns/openuserstatus">
   <soapenv:Header/>
   <soapenv:Body>
      <open:getUserStatusRequest>
         <open:userId>0406581055</open:userId>
         <!--Optional:-->
         <open:userPincode>1055</open:userPincode>
         <open:agencyId>DK-100450</open:agencyId>
      </open:getUserStatusRequest>
   </soapenv:Body>
</soapenv:Envelope> 
 
*/






/** \brief _cdata
*
*/
  private function _cdata($data) { return "<![CDATA[" . $data . "]]>"; }


/** \brief _cdata
*
*/
  private function _set(&$target, $name, $source) {
    if (empty($source)) return;
    $target->$name->_value = $this->_cdata($source);
  }
  

/** \brief _bib_info
 *
 * @param integer $bibno Biblioteks nummeret
 * 
 * @return Bib info
 *
 */
  private function _bib_info($bibno) {
    $bib_info = new bibdk_info($this->config->get_value("oci_credentials", "setup"));
    return $bib_info->get_bib_info($bibno);
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


//==============================================================================


 /** \brief renewLoan - 
  *
  * Request:
  * - userId
  * - userPincode
  * - agencyId
  * - loanId

  * Response:
  * - renewLoanStatus
  * - - loanId
  * - - dateDue
  * - - dateOfExpectedReply
  * - - renewLoanError
  * - renewLoanError
  */
  function renewLoan($param) {
    //$oci = new Oci($this->config->get_value("agency_credentials","setup"));
    //$oci->set_charset("UTF8");
    //$oci->connect();

    $res->error->_value = "invalid_user";
    $one_item->loanId->_value = $some_id;
    $one_item->dateDue->_value = $some_date;
    $res->renewLoanStatus[]->_value = $one_item;

    $ret->renewLoanResponse->_value = $res;
    var_dump($param); die();
    return $ret;
  }

 /** \brief cancelOrder - 
  *
  * Request:
  * - userId
  * - userPincode
  * - agencyId
  * - cancelOrder
  * Response:
  * - cancelOrderStatus
  * - - orderId
  * - - orderCancelled
  * - - cancelOrderError
  * - cancelOrderError
  */
  function cancelOrder($param) {
    var_dump($param); die();
  }

 /** \brief getUserRequest - 
  *
  * Request:
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
  function getUserStatus($param) {

    $userId = $param->userId->_value;
    $userPincode = $param->userPincode->_value;
    $agencyId = $param->agencyId->_value;
    $bib_id = substr($agencyId, 3);

    $fav_info = $this->_bib_info($bib_id);
    $loan_items = array();

    if (strtoupper($fav_info["ncip_lookup_user"]) !== "J") {  // Kan slutbrugerne bede om at se sine bestillinger og laan?
      $response->getUserStatusError->_value = "User is not allowed to make NCIP lookup requests";
    } else {
      $ncip_lookup_user = new ncip();
      $lookup_user = $ncip_lookup_user->request($fav_info["ncip_lookup_user_address"],
                                                array("Ncip" => "LookupUser",
                                                      "FromAgencyId" => "DK-190101",
                                                      "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
                                                      "ToAgencyId" => $agencyId,
                                                      "UserId" => $userId,
                                                      "UserPIN" => $userPincode ) );
      if (isset($lookup_user["Problem"])) {
        $response->getUserStatusError->_value = $lookup_user["Problem"]["Type"];
      } else {
        $lookup_items = array();
        if (is_array($lookup_user["LoanedItem"]))
          foreach ($lookup_user["LoanedItem"] as $loaned_item) {
            $ncip_lookup_item = new ncip();
            $lookup_item = $ncip_lookup_item->request($fav_info["ncip_lookup_user_address"],
                                                      array("Ncip" => "LookupItem",
                                                            "FromAgencyId" => "DK-190101",
                                                            "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
                                                            "ToAgencyId" => $agencyId,
                                                            "UniqueItemId" => $loaned_item["UniqueItemId"] ) );
            if (!isset($lookup_item["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
              $lookup_item = array_merge($lookup_item, $loaned_item);
              $lookup_items[] = $lookup_item;
            }
          }
        $lookup_requests = array();
        if (is_array($lookup_user["RequestedItem"]))
          foreach ($lookup_user["RequestedItem"] as $requested_item) {
            $ncip_lookup_request = new ncip();
            $lookup_request = $ncip_lookup_request->request($fav_info["ncip_lookup_user_address"],
                                                            array("Ncip" => "LookupRequest",
                                                                  "FromAgencyId" => "DK-190101",
                                                                  "FromAgencyAuthentication" => $fav_info["ncip_lookup_user_password"],
                                                                  "ToAgencyId" => $agencyId,
                                                                  "UniqueRequestId" => $requested_item["UniqueRequestId"] ) );
            if (!isset($lookup_request["Problem"])) {  // Ingen fejl meldes, hvis der konstateres problemer...
              $lookup_request = array_merge($requested_item, $lookup_request);
              $lookup_requests[] = $lookup_request;  
            }
          }
      }
      $response->userStatus->_value->loanedItems->_value->loansCount->_value = count($lookup_items);
      if (is_array($lookup_items))
        foreach($lookup_items as $item) {
          unset($loan);
          $loan->loanId->_value = $item["UniqueItemId"]["ItemIdentifierValue"];
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
          $this->_set($loan, "dateDue", $item["DateDue"]);
          $this->_set($loan, "reminderLevel", $item["ReminderLevel"]);
          $this->_set($loan, "loanRecallDate", $item["DateRecalled"]);
          $response->userStatus->_value->loanedItems->_value->loan[]->_value = $loan;
        }
      $response->userStatus->_value->orderedItems->_value->ordersCount->_value = count($lookup_requests);
      if (is_array($lookup_requests))
        foreach($lookup_requests as $request) {
          unset($res);
          $res->orderId->_value = $request["UniqueRequestId"]["RequestIdentifierValue"];
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
          $response->userStatus->_value->orderedItems->_value->order[]->_value = $res;
        }
    }
    $ret->getUserStatusResponse->_value = $response;
    return $ret;
  }
}

/* 
 * MAIN 
 */

$ws=new openUserStatus('openuserstatus.ini');
$ws->handle_request();

?>
