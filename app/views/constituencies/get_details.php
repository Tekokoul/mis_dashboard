<?php
//debug($validated);
?>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">

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
                                <th width="5%"><a class="open-ward-modal" data-constituency-id="<?=$data['constituencies_id'];?>" data-id="new" href="#"><i class="bx bx-plus-medical text-3 me-2"></a></th>
                            </tr>
                            </thead>
                            <?php
                            if(count($data['data'])>0){
                            ?>
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
                                                ? '<td><a class="open-ward-modal" data-constituency-id="'.$row['constituencies_id'].'" data-id="'.$row['id'].'" href="#"><strong>' . display_list_element($properties, $row[$field], $active) . '</strong></a></td>'
                                                : '<td>' . display_list_element($properties, $row[$field], $active) . '</td>';
                                            $first = false;
                                        }
                                    }
                                    ?>
                                    <th width="5%">
                                        <a class="open-ward-modal" data-constituency-id="<?=$data['constituencies_id'];?>" data-id="<?=$row['id']?>" href="#"><i class="bx bx-pencil text-3 me-2"></i></a>
                                        <a class="delete-ward-modal" data-id="<?=$row['id'];?>" href="#deletewardModal"><i class="bx bx-trash text-3 me-2"></i></a>
                                    </th>
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
<div id="wardModal" class="modal-block modal-block-primary mfp-hide"></div>
<div id="deletewardModal" class="modal-block modal-block-primary mfp-hide">
    <section class="card">
        <header class="card-header">
            <h2 class="card-title">Are you sure?</h2>
        </header>
        <div class="card-body">
            <div class="modal-wrapper">
                <div class="modal-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="modal-text">
                    <p class="mb-0">Are you sure that you want to delete this entry?</p>
                </div>
            </div>
        </div>
        <footer class="card-footer">
            <div class="row">
                <div class="col-md-12 text-end">
                    <button class="btn btn-primary modal-confirm">Confirm</button>
                    <button class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </div>
        </footer>
    </section>
</div>