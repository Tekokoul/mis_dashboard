<?php
class coreController extends protectedController{

    public function __construct(Registry $registry) {
        parent::__construct($registry);
        require_once _MODELS_PATH."core.php";
        $this->model = new coreModel($registry);
        $this->update_redirect = $this->update_redirect();
    }

    public function index(){
    }

    public function json_edit(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $model_file_common = _JSON_MODELS_PATH."table.".$validated['model'].".json";
        $model_file_languages = _JSON_MODELS_PATH."table.".$validated['model']."_languages.json";
        $data_file = _JSON_MODELS_PATH."data.".$validated['model'].".json";

        $model = [];

        if(file_exists($model_file_common)) {
            $model["common"] = readJSONFile($model_file_common);
        }
        if(file_exists($model_file_languages)){
            foreach ($this->R->languages as $language=>$properties){
                $model["languages"][$language] = readJSONFile($model_file_languages);
            }
        }
        $data['model_name'] = $validated['model'];
        $data['model'] = $model;
        $data['data'] = readJSONFile($data_file);
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function json_update(){
        $this->checkMethod("POST");

        $values = $this->R->url['query'];
        $table = $values['tablename'];
        unset($values['tablename']);

        $json_table = fopen(_JSON_MODELS_PATH."data.".$table.".json", "w") or die(__FILE__." Unable to open json_models/data.".$table.".json");
        fwrite($json_table, json_encode($values));
        fclose($json_table);
        redirect($this->L("core/json_edit/".$table));
    }

    public function db_list(){
        $this->checkMethod("GET");
        $this->mapRoute("model/page");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "search-term" => FILTER_UNSAFE_RAW,
            "model" => FILTER_UNSAFE_RAW,
            "page" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize(array_merge($this->parts, $this->query), $rules);
        $page = $validated['page'] ?? 1;
        $items_per_page = $_SESSION['user']['settings']['table_rows'] ?? _PAGINATION;

        $model['model_name'] = $validated['model'];
        $model["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['meta_filters'] = $this->model->get_meta_filters($validated['model']);

        $data['model_name'] = $validated['model'];
        $data['fields'] = $this->model->get_list_fields($model);
        $data['search'] = $validated['search-term']??"";
        $filters = [];
        if(is_set($data['meta_filters'])){
            foreach ($data['meta_filters'] as $filter){
                if(array_key_exists($filter['key'], $this->query)) {
                    if ($this->query[$filter['key']] != '%') {
                        // Key is a model-defined column; the VALUE is raw
                        // request input, so it travels as a bound value.
                        // A filter may bring its own clause (Programmes filter by pillar
                        // through their objective); the VALUE is always bound.
                        $filters[] = ['sql' => $filter['sql'] ?? "AND `".$filter['key']."` = ?", 'value' => $this->query[$filter['key']] ?? ""];
                    }
                }
                $data['filter_data'][$filter['key']] = $this->query[$filter['key']] ?? "";
            }
        }

        if(is_set($data['fields'])){
//            $results = ;
            $data = array_merge($data, $this->model->get_list_data($model, $page, $items_per_page, $validated['search-term']??"", $filters));
        } else {
            $data['data'] = [];
        }

        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function db_view(){
        $this->render();
    }

    public function db_add(){
        $this->checkMethod("GET");
        $this->mapRoute("model");
        $this->checkRequired(["model"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $this->AutoInclude($this->model->get_includes($validated['model'], "add"));
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function db_add_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $executed = $this->model->add_data($validated['tablename'], $this->query);
        if(isset($executed['common'])){
            $id_part = ($this->update_redirect=="db_edit") ? "/".$executed['common'] : "";
            redirect($this->L("core/".$this->update_redirect."/".$validated['tablename'].$id_part));
        } else {
            $this->setAnswer(500, "Problem adding the entry.");
        }
    }

    public function db_edit(){
        $this->checkMethod("GET");
        $this->mapRoute("model/id");
        $this->checkRequired(["model", "id"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);

        $data['model_name'] = $validated['model'];
        $data["model"] = $this->model->get_table_fields($validated['model']);
        $data['meta_name'] = $this->model->get_meta_name($validated['model']);
        $data['meta_actions'] = $this->model->get_meta_actions($validated['model']);
        $data['data'] = $this->model->get_data($validated['model'], $validated['id']);
        $this->AutoInclude($this->model->get_includes($validated['model'], "edit"));
        $this->prepare_edit_mode();
        $this->render($data);
    }

    public function db_edit_update(){
        $this->checkMethod("POST");
        $rules = [
            "tablename" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->query, $rules);
        $executed = $this->model->update_data($validated['tablename'], $validated['id'], $this->query);
        if (in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem updating the entry.");
        } else {
            $id_part = ($this->update_redirect=="db_edit") ? "/".$validated['id'] : "";
            redirect($this->L("core/".$this->update_redirect."/".$validated['tablename'].$id_part));
        }
    }

    public function db_delete() {
        // POST only: as a GET, an <img src="/core/db_delete/pm_projects/5"> on
        // any page an administrator opened deleted the row.
        $this->checkMethod("POST");
        $this->mapRoute("model/id");
        $this->checkRequired(["model", "id"], $this->parts);
        $rules = [
            "model" => FILTER_UNSAFE_RAW,
            "id" => FILTER_SANITIZE_NUMBER_INT
        ];
        $validated = $this->sanitize($this->parts, $rules);
        $executed = $this->model->delete_data($validated['model'], $validated['id']);
        if(in_array('false', $executed, true)) {
            $this->setAnswer(500, "Problem deleting entry", [], "json");
        } else {
            $this->setAnswer(200, "Successfully deleted entry <b>".$validated['id']."</b> from model '<b>".$validated['model']."</b>'", [], "json");
        }
    }

    public function password_update(){
        $this->checkMethod("POST");
        // Was `debug($_REQUEST)`, which echoed the submitted password in
        // plaintext into the response body. The endpoint is not implemented;
        // answer honestly instead of leaking the request.
        $this->setAnswer(501, "Password update is not implemented.");
    }

	protected function _sanitizeNumericals() {
		foreach ($this->_numericalValues as $value) {
			if (isset($this->query[$value])) {
				$val = str_replace(',', '.', $this->query[$value]);
				$this->query[$value] = filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, array(
					'flags'=>FILTER_FLAG_ALLOW_FRACTION));
			}
		}
	}

	protected function _getCountryByID($id) {
		$countries = readJSONFile(_JSON_MODELS_PATH."countries_".$this->lang.".json");
		foreach ($countries as $country) {
			if ($country['id'] == $id) {
				return $country['name'];
			}
		}
		return '';
	}

	protected function _getCountryByShort($short) {
		$countries = readJSONFile(_JSON_MODELS_PATH."countries_".$this->lang.".json");
		foreach ($countries as $country) {
			if ($country['alpha2'] == $short) {
				return $country['name'];
			}
		}
		return '';
	}
}