<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Reports <?= isset($data['report']['title']) ? " - '" .display($data['report']['title'])."'" : ""; ?></h2>
</header>
<section class="card card-modern card-modern-table-over-header mb-0">
    <header class="card-header">
        <h2 class="card-title">Choose report</h2>
    </header>
    <div class="card-body" bis_skin_checked="1">

    <div class="form-group row align-items-center pb-3"><label class="col-lg-3 control-label text-lg-end mb-0">Available reports </label><div class="col-lg-8">
            <select id='report_file' class="form-control populate">
        <option value='select'>Found <?=count($data['reports'])?> available reports</option>
        <?php
        foreach ($data['reports'] as $file){
            print "<option value='".$file['value']."'";
            if((isset($data['selected_report'])&&($data['selected_report']!="select"))&&($file['value']==$data['selected_report'])){
                print " selected";
            }
            print ">".$file['title']."</option>";
        }
        ?>
    </select>
        </div>
    </div>
    </div>
</section>

<?php
if(isset($data['selected_report'])&&($data['selected_report']!="select")) {
    ?>
<section class="card card-modern card-modern-table-over-header mb-0">
    <header class="card-header">
        <h2 class="card-title">Select parameters</h2>
    </header>
    <div class="card-body" bis_skin_checked="1">
    <form class="ecommerce-form action-buttons-fixed" name="<?=$data['selected_report']?>_form" action = "<?=$this->L("reports/lists")?>" method = "get" >
        <input type="hidden" name="report" value="<?=$data['selected_report']?>" >
        <input type="hidden" name="run" value="1" >
        <?php
        if (!empty($data['report'])) {
            $html = '';
            foreach ($data['report']['parameters'] as $field=>$value) {
                $prepopulated = $data['data'][$field] ?? "";
                $html .= chooseElement($field, $value, $prepopulated);
            }
            print ($html!="") ? $html : "No available parameters. You can generate the report.";
        }
    ?>

    <div class="row action-buttons">
        <div class="col-12 col-md-auto">
            <?php
            if (!empty($data['result'])) {
            ?>
            <a href="<?=$this->L("reports/xls_export?".http_build_query($data['data']));?>" class="btn btn-default btn-px-4 py-3 d-flex align-items-center line-height-1">
                <i class="bx bxs-file-export text-4 me-2"></i> Download .xlsx
            </a>
            <?php
            }
            ?>
        </div>
        <div class="col-12 col-md-auto ms-md-auto mt-3 mt-md-0 ms-auto">
            <button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading...">
                <i class="bx bx-refresh text-4 me-2"></i> Generate report
            </button>
        </div>
        <div class="col-12 col-md-auto px-md-0 mt-3 mt-md-0">
            <a href="<?=$this->L("reports/lists");?>" class="cancel-button btn btn-default btn-px-4 py-3 line-height-1">Clear</a>
        </div>
    </div>
    </form>
    </div>
</section>

<?php
    if (!empty($data['result'])) {
        ?>
<section class="card card-modern card-modern-table-over-header mb-5">
    <header class="card-header">
        <h2 class="card-title">Results - Total: <?=count($data['result'])?> rows</h2>
    </header>
    <div class="card-body" bis_skin_checked="1">
        <table class="table table-sm table-responsive-lg table-hover mb-0">
        <thead>
        <tr>
<?php
        $columns = array_keys($data['result'][0]);
        $html = '';
        foreach ($columns as $column) {
            $html .= '<th >' . $column . '</th>';
        }
        print $html;
        ?>
        </tr>
        </thead>
        <tbody>
            <?php
        $html = '';
        foreach ($data['result'] as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $html .= '<td>' . $row[$column] . '</td>';
            }
            $html .= '</tr>';
        }
        print $html;

    }
    ?>
    </tbody>
    </table>
    </div>
    </section>
    <?php
}
?>

<script>
    document.getElementById('report_file').onchange = function() {
        window.location = '<?=$this->L("reports/lists?report=");?>' + this.value;
    };
</script>