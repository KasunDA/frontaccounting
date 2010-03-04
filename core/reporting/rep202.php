<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$page_security = 'SA_SUPPLIERANALYTIC';
// ----------------------------------------------------------------
// $ Revision:	2.0 $
// Creator:	Joe Hunt
// date_:	2005-05-19
// Title:	Ages Supplier Analysis
// ----------------------------------------------------------------
$path_to_root="..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");

//----------------------------------------------------------------------------------------------------

print_aged_supplier_analysis();

//----------------------------------------------------------------------------------------------------

function get_invoices($supplier_id, $to)
{
	$todate = date2sql($to);
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;

	// Revomed allocated from sql
    $value = "(".TB_PREF."supp_trans.ov_amount + ".TB_PREF."supp_trans.ov_gst + ".TB_PREF."supp_trans.ov_discount)";
	$due = "IF (".TB_PREF."supp_trans.type=".ST_SUPPINVOICE." OR ".TB_PREF."supp_trans.type=".ST_SUPPCREDIT.",".TB_PREF."supp_trans.due_date,".TB_PREF."supp_trans.tran_date)";
	$sql = "SELECT ".TB_PREF."supp_trans.type,
		".TB_PREF."supp_trans.reference,
		".TB_PREF."supp_trans.tran_date,
		$value as Balance,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) >= 0,$value,0) AS Due,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) >= $PastDueDays1,$value,0) AS Overdue1,
		IF ((TO_DAYS('$todate') - TO_DAYS($due)) >= $PastDueDays2,$value,0) AS Overdue2

		FROM ".TB_PREF."suppliers,
			".TB_PREF."payment_terms,
			".TB_PREF."supp_trans

	   	WHERE ".TB_PREF."suppliers.payment_terms = ".TB_PREF."payment_terms.terms_indicator
			AND ".TB_PREF."suppliers.supplier_id = ".TB_PREF."supp_trans.supplier_id
			AND ".TB_PREF."supp_trans.supplier_id = $supplier_id
			AND ".TB_PREF."supp_trans.tran_date <= '$todate'
			AND ABS(".TB_PREF."supp_trans.ov_amount + ".TB_PREF."supp_trans.ov_gst + ".TB_PREF."supp_trans.ov_discount) > 0.004
			ORDER BY ".TB_PREF."supp_trans.tran_date";


	return db_query($sql, "The supplier details could not be retrieved");
}

//----------------------------------------------------------------------------------------------------

function print_aged_supplier_analysis()
{
    global $comp_path, $path_to_root, $systypes_array;

    $to = $_POST['PARAM_0'];
    $fromsupp = $_POST['PARAM_1'];
    $currency = $_POST['PARAM_2'];
	$summaryOnly = $_POST['PARAM_3'];
    $graphics = $_POST['PARAM_4'];
    $comments = $_POST['PARAM_5'];
	$destination = $_POST['PARAM_6'];
	if ($destination)
		include_once($path_to_root . "/reporting/includes/excel_report.inc");
	else
		include_once($path_to_root . "/reporting/includes/pdf_report.inc");
	if ($graphics)
	{
		include_once($path_to_root . "/reporting/includes/class.graphic.inc");
		$pg = new graph();
	}

	if ($fromsupp == ALL_NUMERIC)
		$from = _('All');
	else
		$from = get_supplier_name($fromsupp);
    $dec = user_price_dec();

	if ($summaryOnly == 1)
		$summary = _('Summary Only');
	else
		$summary = _('Detailed Report');
	if ($currency == ALL_TEXT)
	{
		$convert = true;
		$currency = _('Balances in Home Currency');
	}
	else
		$convert = false;
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;
	$nowdue = "1-" . $PastDueDays1 . " " . _('Days');
	$pastdue1 = $PastDueDays1 + 1 . "-" . $PastDueDays2 . " " . _('Days');
	$pastdue2 = _('Over') . " " . $PastDueDays2 . " " . _('Days');

	$cols = array(0, 100, 130, 190,	250, 320, 385, 450,	515);

	$headers = array(_('Supplier'),	'',	'',	_('Current'), $nowdue, $pastdue1,$pastdue2,
		_('Total Balance'));

	$aligns = array('left',	'left',	'left',	'right', 'right', 'right', 'right',	'right');

    $params =   array( 	0 => $comments,
    				    1 => array('text' => _('End Date'), 'from' => $to, 'to' => ''),
    				    2 => array('text' => _('Supplier'), 'from' => $from, 'to' => ''),
    				    3 => array('text' => _('Currency'),'from' => $currency,'to' => ''),
                    	4 => array('text' => _('Type'), 'from' => $summary,'to' => ''));

	if ($convert)
		$headers[2] = _('currency');
    $rep = new FrontReport(_('Aged Supplier Analysis'), "AgedSupplierAnalysis", user_pagesize());

    $rep->Font();
    $rep->Info($params, $cols, $headers, $aligns);
    $rep->Header();

	$total = array();
	$total[0] = $total[1] = $total[2] = $total[3] = $total[4] = 0.0;
	$PastDueDays1 = get_company_pref('past_due_days');
	$PastDueDays2 = 2 * $PastDueDays1;

	$nowdue = "1-" . $PastDueDays1 . " " . _('Days');
	$pastdue1 = $PastDueDays1 + 1 . "-" . $PastDueDays2 . " " . _('Days');
	$pastdue2 = _('Over') . " " . $PastDueDays2 . " " . _('Days');

	$sql = "SELECT supplier_id, supp_name AS name, curr_code FROM ".TB_PREF."suppliers";
	if ($fromsupp != ALL_NUMERIC)
		$sql .= " WHERE supplier_id=".db_escape($fromsupp);
	$sql .= " ORDER BY supp_name";
	$result = db_query($sql, "The suppliers could not be retrieved");

	while ($myrow=db_fetch($result))
	{
		if (!$convert && $currency != $myrow['curr_code'])
			continue;
		$rep->fontSize += 2;
		$rep->TextCol(0, 2,	$myrow['name']);
		if ($convert)
		{
			$rate = get_exchange_rate_from_home_currency($myrow['curr_code'], $to);
			$rep->TextCol(2, 3,	$myrow['curr_code']);
		}
		else
			$rate = 1.0;
		$rep->fontSize -= 2;
		$supprec = get_supplier_details($myrow['supplier_id'], $to);
		foreach ($supprec as $i => $value)
			$supprec[$i] *= $rate;
		$total[0] += ($supprec["Balance"] - $supprec["Due"]);
		$total[1] += ($supprec["Due"]-$supprec["Overdue1"]);
		$total[2] += ($supprec["Overdue1"]-$supprec["Overdue2"]);
		$total[3] += $supprec["Overdue2"];
		$total[4] += $supprec["Balance"];
		$str = array($supprec["Balance"] - $supprec["Due"],
			$supprec["Due"]-$supprec["Overdue1"],
			$supprec["Overdue1"]-$supprec["Overdue2"],
			$supprec["Overdue2"],
			$supprec["Balance"]);
		for ($i = 0; $i < count($str); $i++)
			$rep->AmountCol($i + 3, $i + 4, $str[$i], $dec);
		$rep->NewLine(1, 2);
		if (!$summaryOnly)
		{
			$res = get_invoices($myrow['supplier_id'], $to);
    		if (db_num_rows($res)==0)
				continue;
    		$rep->Line($rep->row + 4);
			while ($trans=db_fetch($res))
			{
				$rep->NewLine(1, 2);
        		$rep->TextCol(0, 1, $systypes_array[$trans['type']], -2);
				$rep->TextCol(1, 2,	$trans['reference'], -2);
				$rep->TextCol(2, 3,	sql2date($trans['tran_date']), -2);
				foreach ($trans as $i => $value)
					$trans[$i] *= $rate;
				$str = array($trans["Balance"] - $trans["Due"],
					$trans["Due"]-$trans["Overdue1"],
					$trans["Overdue1"]-$trans["Overdue2"],
					$trans["Overdue2"],
					$trans["Balance"]);
				for ($i = 0; $i < count($str); $i++)
					$rep->AmountCol($i + 3, $i + 4, $str[$i], $dec);
			}
			$rep->Line($rep->row - 8);
			$rep->NewLine(2);
		}
	}
	if ($summaryOnly)
	{
    	$rep->Line($rep->row  + 4);
    	$rep->NewLine();
	}
	$rep->fontSize += 2;
	$rep->TextCol(0, 3,	_('Grand Total'));
	$rep->fontSize -= 2;
	for ($i = 0; $i < count($total); $i++)
	{
		$rep->AmountCol($i + 3, $i + 4, $total[$i], $dec);
		if ($graphics && $i < count($total) - 1)
		{
			$pg->y[$i] = abs($total[$i]);
		}
	}
   	$rep->Line($rep->row  - 8);
   	$rep->NewLine();
   	if ($graphics)
   	{
   		global $decseps, $graph_skin;
		$pg->x = array(_('Current'), $nowdue, $pastdue1, $pastdue2);
		$pg->title     = $rep->title;
		$pg->axis_x    = _("Days");
		$pg->axis_y    = _("Amount");
		$pg->graphic_1 = $to;
		$pg->type      = $graphics;
		$pg->skin      = $graph_skin;
		$pg->built_in  = false;
		$pg->fontfile  = $path_to_root . "/reporting/fonts/Vera.ttf";
		$pg->latin_notation = ($decseps[$_SESSION["wa_current_user"]->prefs->dec_sep()] != ".");
		$filename = $comp_path.'/'.user_company(). "/pdf_files/test.png";
		$pg->display($filename, true);
		$w = $pg->width / 1.5;
		$h = $pg->height / 1.5;
		$x = ($rep->pageWidth - $w) / 2;
		$rep->NewLine(2);
		if ($rep->row - $h < $rep->bottomMargin)
			$rep->Header();
		$rep->AddImage($filename, $x, $rep->row - $h, $w, $h);
	}
    $rep->End();
}

?>