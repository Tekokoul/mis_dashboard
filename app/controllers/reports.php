<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class reportsController extends protectedController{

    public function lists() {
        $this->checkMethod("GET");
        $rules = [
            "report" => FILTER_UNSAFE_RAW,
            "run" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = array_merge($this->query, $this->sanitize($this->query, $rules));
        if(isset($validated['report'])){
            $report_file = _REPORTS_PATH."report.".$validated['report'].".json";
            if(file_exists($report_file)){
                $data['report'] = readJSONFile($report_file);
                $data['selected_report'] = $validated['report'];
            }
        }

        $files = array_diff(scandir(_REPORTS_PATH), ['.', '..']);
        $data['reports'] = [];
        foreach ($files as $file){
            $path_parts = pathinfo(_REPORTS_PATH.$file);
            $temp = readJSONFile(_REPORTS_PATH.$file);
            $data['reports'][] = [
                "title" => $temp['title'],
                "value" => str_replace("report.","",$path_parts['filename'])
            ];
        }
        $data['data'] = $validated;

        if(isset($validated['run'])){
            $query = $data['report']['query'];
            foreach ($data['report']['parameters'] as $parameter => $value) {
                $query = str_replace("@@$parameter@@", $data['data'][$parameter], $query);
            }
            $data['result'] = $this->DB->MQ($query, "all");
        }

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function xls_export(){
        $this->checkMethod("GET");
        $this->checkRequired(['report'], $this->query);
        $rules = [
            "report" => FILTER_UNSAFE_RAW
        ];
        $validated = array_merge($this->query, $this->sanitize($this->query, $rules));
        if(isset($validated['report'])){
            $report_file = _REPORTS_PATH."report.".$validated['report'].".json";
            if(file_exists($report_file)){
                $report = readJSONFile($report_file);
            }
        }
        $data = $validated;
        if(isset($validated['run'])){
            $query = $report['query'];
            foreach ($report['parameters'] as $parameter => $value) {
                $query = str_replace("@@$parameter@@", $data[$parameter], $query);
            }
            $result = $this->DB->MQ($query, "all");
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_keys($result[0]);
        $sheet->fromArray(array($headers), null, 'A1');
        $row = 2;
        foreach ($result as $data) {
            $sheet->fromArray([$data], null, 'A' . $row);
            $row++;
        }
        $filePath = sluggify($report['title']." ".date("Y-m-d H:i:s")).'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Send the file to the browser for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$filePath.'"');
        header('Cache-Control: max-age=0');
        readfile($filePath);
        exit;
    }
}