<?php
//debug($data);
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Configure JSON files</h2>
</header>
<div class="row">
    <div class="col-3">
        <section class="card card-modern">
            <header class="card-header">
                <h2 class="card-title"><?=$data['filename']??"List of JSON files";?></h2>
            </header>
            <div class="card-body">
                <div id="jsontree">
                        <ul>
                            <li data-jstree='{ "opened" : true }'>
                                Models' settings
                                <ul>
                                        <?php
                                        foreach ($data['models_settings'] as $file){
                                            $file = basename($file, ".json");
                                        ?>
                                        <li data-jstree='{ "type" : "file" }'>
                                        <a href="<?=$this->L("admin/configure_json/models_settings/".$file)?>" ><?=$file;?></a>
                                        </li>
                                        <?php
                                        }
                                        ?>
                                    <li data-jstree='{ "icon" : "fas fa-plus" }'>
                                        <a href="<?=$this->L("admin/configure_json/models_settings/new");?>" >Add file</a>
                                    </li>
                                </ul>
                            </li>
                            <li data-jstree='{ "opened" : true }'>
                                Reports
                                <ul>
                                    <?php
                                    foreach ($data['reports'] as $file){
                                        $file = basename($file, ".json");
                                        ?>
                                        <li data-jstree='{ "type" : "file" }'>
                                            <a href="<?=$this->L("admin/configure_json/reports/".$file)?>" ><?=$file;?></a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                    <li data-jstree='{ "icon" : "fas fa-plus" }'>
                                        <a href="<?=$this->L("admin/configure_json/reports/new");?>" >Add file</a>
                                    </li>
                                </ul>
                            </li>
                            <li data-jstree='{ "opened" : false }'>
                                JSON models
                                <ul>
                                    <?php
                                    foreach ($data['json_models'] as $file){
                                        $file = basename($file, ".json");
                                        ?>
                                        <li data-jstree='{ "type" : "file" }'>
                                            <a href="<?=$this->L("admin/configure_json/json_models/".$file)?>" ><?=$file;?></a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                </ul>
                            </li>
                            <li data-jstree='{ "opened" : true }'>
                                Menus
                                <ul>
                                    <?php
                                    foreach ($data['menus'] as $file){
                                        $file = basename($file, ".json");
                                        ?>
                                        <li data-jstree='{ "type" : "file" }'>
                                            <a href="<?=$this->L("admin/configure_json/menus/".$file)?>" ><?=$file;?></a>
                                        </li>
                                        <?php
                                    }
                                    ?>
                                    <li data-jstree='{ "icon" : "fas fa-plus" }'>
                                        <a href="<?=$this->L("admin/configure_json/menus/new");?>" >Add file</a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                </div>
            </div>
        </section>    </div>
    <div class="col-9">
        <form class="form-horizontal" action="<?=$this->L("admin/json_update");?>" method="post">
            <section class="card card-modern">
                <div class="card-body">
                    <input type="hidden" id="folder" name="folder" value="<?=$data['folder']??"";?>">
                    <input type="hidden" id="folder" name="originalmodel" value="<?=$data['filename']??"";?>">

                    <?php
                    if(isset($data['filename'])){
                        $filename = ($data['filename']=="new") ? "" : $data['filename'];
                    } else {
                        $filename = "";
                    }

                    ?>
                    <div class="form-group row align-items-center pb-3">
                        <label class="col-lg-1 control-label text-lg-end mb-0">Filename</label><div class="col-lg-11">
                            <input type="text" class="form-control" id="model" name="model" placeholder="Please enter new file..." value="<?=$filename;?>">
                        </div>
                    </div>
                    <div class="form-group row pb-3 pb-3">
                        <div class="col-md-10" style="width: 100%;">
                        <textarea class="form-control" rows="50" id="content" name="content"
                                  data-plugin-codemirror
                                  data-plugin-options='{ "mode": "text/x-php", "theme":"material-palenight" }'><?=$data['content']??"";?></textarea>
                        </div>
                    </div>
                </div>
                <?php
                if(isset($data['filename'])){
                    ?>
                <footer class="card-footer text-end">

                    <button class="submit-button btn btn-primary btn-px-4 py-3 font-weight-semibold line-height-1">Submit</button>
                    <button type="reset" class="btn btn-default btn-px-4 py-3 line-height-1">Reset</button>
                </footer>
                <?php
                }
                ?>
            </section>
        </form>
    </div>
</div>