<section class="card card-modern mb-5">
    <div class="card-body">
        <div class="row">
            <div>
                <?php
                $html = "";
                foreach ($data['model']['common'] as $field=>$value) {
                    $prepopulated = $data['data'][$field] ?? "";
                    $html .= chooseElement('additional_tables['.$data['model_name'].']['.$field.']', $value, $prepopulated);
                }
                print $html;
                ?>
            </div>
        </div>
    </div>
</section>