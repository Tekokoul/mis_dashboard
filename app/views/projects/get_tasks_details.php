<?php
//debug($data['data']);
?>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <?php
                    if(count($data['data'])>0){
                        ?>
                        <div class="table-responsive">
                            <table class="table table-ecommerce-simple table-borderless table-striped mb-0" id="datatable-list" style="min-width: 640px;">
                                <thead>
                                <tr>
                                    <th width="4%">A/A</th>
                                    <?php
                                    foreach ($data['fields'] as $field => $properties){
                                        if(isset($properties['appear_in_list'])){
                                            $title = (isset($properties['title'])) ? $properties['title'] : $field;
                                            ?>
                                            <th width="<?=$properties['list_width'];?>%"><?=ucfirst($title)?></th>
                                            <?php
                                        }
                                    }
                                    ?>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $aa = (($data['page']-1)*$data['items'])+1;
                                foreach ($data['data'] as $row) {
                                    ?>
                                    <tr>
                                        <td><?=$aa;?></td>
                                        <?php
                                        $first = true;
                                        foreach ($data['fields'] as $field => $properties) {
                                            if (isset($properties['appear_in_list'])) {

                                                $active = (isset($row['active'])) ? $row['active'] : true;
                                                print ($first)
                                                    ? '<td><a class="open-task-modal" data-project-id="'.$row['project_id'].'" data-member-id="'.$row['member_id'].'" data-id="'.$row['task_id'].'" href="#"><strong>' . display_list_element($properties, $row[$field], $active) . '</strong></a></td>'
                                                    : '<td>' . display_list_element($properties, $row[$field], $active) . '</td>';
                                                $first = false;
                                            }
                                        }
                                        ?>
                                    </tr>
                                    <?php
                                    $aa++;
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="taskModal" class="modal-block modal-block-primary mfp-hide"></div>