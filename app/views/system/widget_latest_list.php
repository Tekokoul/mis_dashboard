<?php
        $page_link_prefix = $data['settings']['latest_list']['prefix'];
?>
<div class="row">
    <div class="col">
        <div class="card card-modern card-modern-table-over-header">
            <div class="card-header">
                <h2 class="card-title"><?=$data['settings']['latest_list']['title']?></h2>
            </div>
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <div class="datatable-header">
                        <div class="row align-items-center mb-5">
                        </div>
                    </div>

                    <table class="table table-ecommerce-simple table-no-more table-borderless table-striped mb-0">

                        <thead>
                        <tr>
                            <?php
                            foreach ($data['settings']['latest_list']['fields'] as $field){
                                if($field['display']){
                                    ?>
                                    <th width="<?=$field['width'];?>%"><?=$field['title']?></th>
                                    <?php
                                }
                            }
                            ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($data['latest_data'] as $row){

                            ?>
                            <tr>
                                <?php
                                foreach ($data['settings']['latest_list']['fields'] as $field=>$settings){
                                    if($settings['display']){
                                        if(isset($settings['link'])){
                                            $link = $this->L($this->model->dynamic_link($settings['link'], $row));
                                            ?>
                                            <td data-title="<?=$settings['title']?>"><a href="<?=$link?>"><strong><?=$settings['function']($row[$field]);?></strong></a></td>
                                            <?php
                                        } else {
                                            ?>
                                            <td data-title="<?=$settings['title']?>"><?=$settings['function']($row[$field]);?></td>
                                            <?php
                                        }
                                    }
                                }
                                ?>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>