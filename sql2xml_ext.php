<?php
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Christian Stocker <chregu@phant.ch>                         |
// +----------------------------------------------------------------------+
//
// $Id$

require_once ("XML/sql2xml.php");

/**
 *  This class shows with an example, how the base sql2xml-class
 *   could be extended.
 *
 * Usage example
 *
 * include_once("XML/sql2xml_ext.php");
 * $options= array( user_options => array (xml_seperator =>"_",
 *                                       element_id => "id"),
 * );
 * $sql2xml = new xml_sql2xml_ext("mysql://root@localhost/xmltest");
 * $sql2xml->SetOptions($options);
 * $xmlstring = $sql2xml->getxml("select * from bands");

 * more examples and outputs on
 *   http://php.chregu.tv/sql2xml/
 *   for the time being
 *
 * @author   Christian Stocker <chregu@nomad.ch>
 * @version  $Id$
 */
class XML_sql2xml_ext extends XML_sql2xml {


    function XML_sql2xml_ext ($dsn=Null,$root = "root")
    {
        $this->XML_sql2xml($dsn,$root);
        // DefaultValues for user_options

        $user_options = array (
               'xml_seperator' =>"_",
               'element_id' => "ID",
               'print_empty_ids' => True,
               'selected_id' => array(),
               'field_translate' => array(),
               'attributes' => array(),
               'TableNameForRowTags' => True
        );

       $this->setOptions(array("user_options"=>$user_options));

    }

    function insertNewRow ($parent_row, $res, $key, &$tableInfo)
    {

        if (!$tableInfo[$key]["table"] ) {
            $tableInfo[$key]["table"] = $this->tagNameResult;
        }
        if ($this->user_options['element_id'] && !$res[$tableInfo["id"][$tableInfo[$key]["table"]]] && !$this->user_options['print_empty_ids'])
        {
            return Null;
        }
        if ( !$this->user_options['TableNameForRowTags'])
        {
               $new_row= $parent_row->new_child($this->tagNameRow,Null);
        }
        else
        {
            $new_row= $parent_row->new_child($tableInfo[$key]["table"],Null);
        }
        /* make an unique ID attribute in the row element with tablename.id if there's an id
               otherwise just make an unique id with the php-function, just that there's a unique id for this row.
                CAUTION: This ID changes every time ;) (if no id from db-table)
               */
        $this->SetAttribute($new_row,"type","row");

        if ($res[$tableInfo["id"][$tableInfo[$key]["table"]]])
        {
        /* make attribute selected if ID = selected_id OR tableName.ID = selected_id. for the second case
            you can give an array for multiple selected entries */

        if ($res[$tableInfo["id"][$tableInfo[$key]["table"]]] == $this->user_options['selected_id']
            || $tableInfo[$key]["table"].$res[$tableInfo["id"][$tableInfo[$key]["table"]]] == $this->user_options['selected_id']
            || (is_array($this->user_options['selected_id']) && in_array($tableInfo[$key]["table"].$res[$tableInfo["id"][$tableInfo[$key]["table"]]],$this->user_options['selected_id']))
            ||    $this->user_options['selected_id'] == "all" 
            ||  ($this->user_options['selected_id'] == "first") && !isset($this->table_selected[$tableInfo[$key]["table"]])
           )
            {
                $this->SetAttribute($new_row,"selected", "selected");
                $this->table_selected[$tableInfo[$key]["table"]] = True;
            }
            $this->SetAttribute($new_row,"ID", utf8_encode($tableInfo[$key]["table"] . $res[$tableInfo["id"][$tableInfo[$key]["table"]]]));
        }
        else
        {
            $this->IDcounter[$tableInfo[$key]["table"]]++;
            $this->SetAttribute($new_row,"ID", $tableInfo[$key]["table"].$this->IDcounter[$tableInfo[$key]["table"]]);

        }

        return $new_row;
    }


    function insertNewResult (&$tableInfo) {

        if (isset($this->user_options["result_root"]))
            $result_root = $this->user_options["result_root"];
        elseif (isset($tableInfo[0]["table"]))
            $result_root = $tableInfo[0]["table"];
        else
            $result_root = "resultset";

        if ($this->xmlroot)
            $xmlroot=$this->xmlroot->new_child($result_root,Null);
        else
            $xmlroot= $this->xmldoc->add_root($result_root);
        $this->SetAttribute($xmlroot,"type","resultset");

        return $xmlroot;
    }


    function insertNewElement ($parent, $res, $key, &$tableInfo, &$subrow) {

        if (is_array($this->user_options["attributes"]) && in_array($tableInfo[$key]["name"],$this->user_options["attributes"])) {
            $subrow=$this->SetAttribute($parent,$tableInfo[$key]["name"],$this->xml_encode($res[$key]));
        }
        elseif ($this->user_options["xml_seperator"])
        {
           // initialize some variables to get rid of warning messages
            $beforetags = "";
            $before[-1] = Null;
            //the preg should be only done once...            
            $i = 0;
            preg_match_all("/([^" . $this->user_options["xml_seperator"] . "]+)" . $this->user_options['xml_seperator'] . "*/", $tableInfo[$key]["name"], $regs);

            if (isset($regs[1][-1]))
            {
                $subrow[$regs[1][-1]] = $parent;
            }
            else 
            {
                $subrow[Null] = $parent;
            }
            // here we separate db fields to subtags.

            for ($i = 0; $i < (count($regs[1]) - 1); $i++)
            {
                $beforetags .=$regs[1][$i]."_";
                $before[$i] = $beforetags;
                if ( ! isset($subrow[$before[$i]]) ) {
                    $subrow[$before[$i]] = $subrow[$before[$i - 1]]->new_child($regs[1][$i], NULL);
                }
            }
            $subrows = $subrow[$before[$i - 1]]->new_child($regs[1][$i], $this->xml_encode($res[$key]));

        }
        else
        {
            $subrow=$parent->new_child($tableInfo[$key]["name"], $this->xml_encode($res[$key]));
        }

    }

    function addTableinfo($key, $value, &$tableInfo) {
        
        if (!isset($tableInfo['id'][$value["table"]]) && $value["name"] == $this->user_options["element_id"] )
        {
            $tableInfo['id'][$value["table"]]= $key;
        }
        if (isset($this->user_options["field_translate"][$value["name"]])) {
            $tableInfo[$key]["name"] = $this->user_options["field_translate"][$value["name"]];
        }
    }

    // A wrapper for set setattr/set_attribute, since the function changed in php 4.0.6...
    function SetAttribute ($node,$name,$value) {
        if (method_exists($node,"Set_attribute"))
        {
            return $node->Set_Attribute($name,$value);
        }
        else {
            return $node->setattr($name,$value);
        }
    }

}
?>
