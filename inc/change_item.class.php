<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

// Relation between Changes and Items
class Change_Item extends CommonDBRelation{


   // From CommonDBRelation
   public $itemtype_1 = 'Change';
   public $items_id_1 = 'changes_id';

   public $itemtype_2 = 'itemtype';
   public $items_id_2 = 'items_id';


   function prepareInputForAdd($input) {

      if (empty($input['itemtype'])
          || empty($input['items_id'])
          || $input['items_id']==0
          || empty($input['changes_id'])
          || $input['changes_id']==0) {
         return false;
      }

      // Avoid duplicate entry
      $restrict = "`changes_id` = '".$input['changes_id']."'
                   AND `itemtype` = '".$input['itemtype']."'
                   AND `items_id` = '".$input['items_id']."'";
      if (countElementsInTable($this->getTable(),$restrict)>0) {
         return false;
      }
      return $input;
   }


   static function countForItem(CommonDBTM $item) {

      $restrict = "`glpi_changes_items`.`changes_id` = `glpi_changes`.`id`
                   AND `glpi_documents_items`.`items_id` = '".$item->getField('id')."'
                   AND `glpi_documents_items`.`itemtype` = '".$item->getType()."'".
                   getEntitiesRestrictRequest(" AND ", "glpi_changes", '', '', true);

      $nb = countElementsInTable(array('glpi_changes_items', 'glpi_changes'), $restrict);

      return $nb ;
   }


   /**
    * Print the HTML array for Items linked to a change
    *
    * @param $change Change object
    *
    * @return Nothing (display)
   **/
   static function showForChange(Change $change) {
      global $DB, $CFG_GLPI;

      $instID = $change->fields['id'];

      if (!$change->can($instID,'r')) {
         return false;
      }
      $canedit = $change->can($instID,'w');
      $rand    = mt_rand();

      $query = "SELECT DISTINCT `itemtype`
                FROM `glpi_changes_items`
                WHERE `glpi_changes_items`.`changes_id` = '$instID'
                ORDER BY `itemtype`";

      $result = $DB->query($query);
      $number = $DB->numrows($result);

      echo "<div class='center'><table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='5'>";
      if ($DB->numrows($result)==0) {
         _e('No associated item');
      } else if ($DB->numrows($result)==1) {
         _e('Associated item');
      } else {
         _e('Associated items');
      }
      echo "</th></tr>";
      if ($canedit) {
         echo "</table></div>";

         echo "<form method='post' name='itemchange_form$rand' id='itemchange_form$rand' action=\"".
                $CFG_GLPI["root_doc"]."/front/change_item.form.php\">";
         echo "<div class='spaced'>";
         echo "<table class='tab_cadre_fixe'>";
         // massive action checkbox
         echo "<tr><th>&nbsp;</th>";
      } else {
         echo "<tr>";
      }
      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Name')."</th>";
      echo "<th>".__('Serial number')."</th>";
      echo "<th>".__('Inventory number')."</th></tr>";

      $totalnb = 0;
      for ($i=0 ; $i<$number ; $i++) {
         $itemtype = $DB->result($result, $i, "itemtype");
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }
         if ($item->canView()) {
            $itemtable = getTableForItemType($itemtype);
            $query = "SELECT `$itemtable`.*,
                             `glpi_changes_items`.`id` AS IDD,
                             `glpi_entities`.`id` AS entity
                      FROM `glpi_changes_items`,
                           `$itemtable`";

            if ($itemtype != 'Entity') {
               $query .= " LEFT JOIN `glpi_entities`
                                 ON (`$itemtable`.`entities_id`=`glpi_entities`.`id`) ";
            }

            $query .= " WHERE `$itemtable`.`id` = `glpi_changes_items`.`items_id`
                              AND `glpi_changes_items`.`itemtype` = '$itemtype'
                              AND `glpi_changes_items`.`changes_id` = '$instID'";

            if ($item->maybeTemplate()) {
               $query .= " AND `$itemtable`.`is_template` = '0'";
            }

            $query .= getEntitiesRestrictRequest(" AND", $itemtable, '', '',
                                                 $item->maybeRecursive())."
                      ORDER BY `glpi_entities`.`completename`, `$itemtable`.`name`";

            $result_linked = $DB->query($query);
            $nb            = $DB->numrows($result_linked);

            for ($prem=true ; $data=$DB->fetch_assoc($result_linked) ; $prem=false) {
               $ID = "";
               if ($_SESSION["glpiis_ids_visible"] || empty($data["name"])) {
                  $ID = " (".$data["id"].")";
               }
               $link = Toolbox::getItemTypeFormURL($itemtype);
               $name = "<a href=\"".$link."?id=".$data["id"]."\">".$data["name"]."$ID</a>";

               echo "<tr class='tab_bg_1'>";
               if ($canedit) {
                  $sel = "";
                  if (isset($_GET["select"]) && $_GET["select"]=="all") {
                     $sel = "checked";
                  }
                  echo "<td width='10'>";
                  echo "<input type='checkbox' name='item[".$data["IDD"]."]' value='1' $sel></td>";
               }
               if ($prem) {
                  $name = $item->getTypeName($nb);
                  echo "<td class='center top' rowspan='$nb'>".
                         ($nb>1 ? sprinf(__('%1$s: %2$d'), $name, $nb)
                                : sprinf(__('%s'), $name))."</td>";
               }
               echo "<td class='center'>";
               echo Dropdown::getDropdownName("glpi_entities", $data['entity'])."</td>";
               echo "<td class='center".
                      (isset($data['is_deleted']) && $data['is_deleted'] ? " tab_bg_2_2'" : "'");
               echo ">".$name."</td>";
               echo "<td class='center'>".
                      (isset($data["serial"])? "".$data["serial"]."" :"-")."</td>";
               echo "<td class='center'>".
                      (isset($data["otherserial"])? "".$data["otherserial"]."" :"-")."</td>";
               echo "</tr>";
            }
            $totalnb += $nb;
         }
      }
      echo "<tr class='tab_bg_2'>";
      echo "<td class='center' colspan='2'>".
             ($totalnb>0? sprintf(__('Total = %s'), $totalnb) : "&nbsp;");
      echo "</td><td colspan='4'>&nbsp;</td></tr> ";

      if ($canedit) {
         echo "<tr class='tab_bg_1'><td colspan='4' class='right'>";
         $types = array();
         foreach ($change->getAllTypesForHelpdesk() as $key => $val) {
            $types[] = $key;
         }
         Dropdown::showAllItems("items_id", 0, 0,
                                ($change->fields['is_recursive']?-1:$change->fields['entities_id']),
                                $types);
         echo "</td><td class='center'>";
         echo "<input type='submit' name='add' value=\"".__s('Add')."\" class='submit'>";
         echo "</td><td>&nbsp;</td></tr>";
         echo "</table>";

         Html::openArrowMassives("itemchange_form$rand", true);
         echo "<input type='hidden' name='changes_id' value='$instID'>";
         Html::closeArrowMassives(array('delete' => __('Delete')));

      } else {
         echo "</table>";
      }
      echo "</div></form>";
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case 'Change' :
               return __('Items');
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='Change') {
         self::showForChange($item);
      }
      return true;
   }

}
?>