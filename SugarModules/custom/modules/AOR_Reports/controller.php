<?php

/**
 * Developer: Ruvin Roshan (c) - 2018
 *
 * Override method to correct export data
 *
 */

require_once "modules/AOR_Reports/controller.php";

class CustomAOR_ReportsController extends AOR_ReportsController {

	function encloseForCSV($field) {
		return '"' . $field . '"';
	}

	protected function action_export() {

		if (!$this->bean->ACLAccess('Export')) {
			SugarApplication::appendErrorMessage(translate('LBL_NO_ACCESS', 'ACL'));
			SugarApplication::redirect("index.php?module=AOR_Reports&action=DetailView&record=" . $this->bean->id);
			sugar_die('');
		}
		$this->bean->user_parameters = requestToUserParameters();
		// RR
		$this->build_report_csv2($this->bean);
		//$this->bean->build_report_csv();
		die;
	}

	function build_report_csv2($thiss) {
		global $beanList;

		ini_set('zlib.output_compression', 'Off');

		ob_start();
		require_once 'include/export_utils.php';

		$delimiter = getDelimiter();
		$csv = '';
		//text/comma-separated-values

		$sql = "SELECT id FROM aor_fields WHERE aor_report_id = '" . $thiss->id . "' AND deleted = 0 ORDER BY field_order ASC";
		$result = $thiss->db->query($sql);

		$fields = array();
		$i = 0;
		while ($row = $thiss->db->fetchByAssoc($result)) {

			$field = new AOR_Field();
			$field->retrieve($row['id']);

			$path = unserialize(base64_decode($field->module_path));
			$field_bean = new $beanList[$thiss->report_module]();
			$field_module = $thiss->report_module;
			$field_alias = $field_bean->table_name;

			if ($path[0] != $thiss->report_module) {
				foreach ($path as $rel) {
					if (empty($rel)) {
						continue;
					}
					$field_module = getRelatedModule($field_module, $rel);
					$field_alias = $field_alias . ':' . $rel;
				}
			}
			$label = str_replace(' ', '_', $field->label) . $i;
			$fields[$label]['field'] = $field->field;
			$fields[$label]['display'] = $field->display;
			$fields[$label]['function'] = $field->field_function;
			$fields[$label]['module'] = $field_module;
			$fields[$label]['alias'] = $field_alias;
			$fields[$label]['params'] = $field->format;

			if ($field->display) {
				$csv .= $this->encloseForCSV($field->label);
				$csv .= $delimiter;
			}
			++$i;
		}

		$sql = $thiss->build_report_query();
		$result = $thiss->db->query($sql);

		while ($row = $thiss->db->fetchByAssoc($result)) {
			$csv .= "\r\n";
			foreach ($fields as $name => $att) {
				$currency_id = isset($row[$att['alias'] . '_currency_id']) ? $row[$att['alias'] . '_currency_id'] : '';
				if ($att['display']) {
					if ($att['function'] != '' || $att['params'] != '') {
						$csv .= $this->encloseForCSV($row[$name]);
					} else {
						$csv .= $this->encloseForCSV(trim(strip_tags(getModuleField($att['module'], $att['field'],
							$att['field'], 'DetailView', $row[$name], '', $currency_id))));
					}
					$csv .= $delimiter;
				}
			}
		}

		$csv1 = $GLOBALS['locale']->translateCharset($csv, 'UTF-8', $GLOBALS['locale']->getExportCharset());
		//RR
		$csv = html_entity_decode($csv1, ENT_QUOTES, 'UTF-8');
		//END
		ob_clean();
		header("Pragma: cache");
		header("Content-type: text/comma-separated-values; charset=" . $GLOBALS['locale']->getExportCharset());
		header("Content-Disposition: attachment; filename=\"{$thiss->name}.csv\"");
		//RR
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		//END
		header("Content-transfer-encoding: binary");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . TimeDate::httpTime());
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Content-Length: " . mb_strlen($csv, '8bit'));
		if (!empty($sugar_config['export_excel_compatible'])) {
			$csv = chr(255) . chr(254) . mb_convert_encoding($csv, 'UTF-16LE', 'UTF-8');
		}
		print $csv;

		sugar_cleanup(true);
	}

}