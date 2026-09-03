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
                                    <th width="4%">#</th>
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
                                    <th width="5%"><a class="open-task-modal" data-project-id="<?=$data['project_id'];?>" data-id="new" href="#" aria-label="Add a task"><i class="bx bx-plus-medical text-3 me-2"></i></a></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // The table and its wrapper are always closed, whatever the row
                                // count: an activity with no task used to leave them open, and the
                                // parser swallowed both modals below into the unclosed table.
                                // The header stays so the "+" is still there to add the first one.
                                if(count($data['data'])>0){
                                $aa = (((int)($data['page'] ?? 1)) - 1) * ((int)($data['items'] ?? 0)) + 1;
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
                                                    ? '<td><a class="open-task-modal" data-project-id="'.$row['project_id'].'" data-id="'.$row['id'].'" href="#"><strong>' . display_list_element($properties, $row[$field], $active) . '</strong></a></td>'
                                                    : '<td>' . display_list_element($properties, $row[$field], $active) . '</td>';
                                                $first = false;
                                            }
                                        }
                                        ?>
                                        <td width="5%">
                                            <a class="open-task-modal" data-project-id="<?=$data['project_id'];?>" data-id="<?=$row['id']?>" href="#" aria-label="Edit"><i class="bx bx-pencil text-3 me-2"></i></a>
                                            <a class="delete-task-modal" data-id="<?=$row['id'];?>" href="#deleteTaskModal" aria-label="Delete"><i class="bx bx-trash text-3 me-2"></i></a>
                                        </td>
                                    </tr>
                                    <?php
                                    $aa++;
                                }
                                } else {
                                    $cols = 2;
                                    foreach ($data['fields'] as $properties) { if (isset($properties['appear_in_list'])) { $cols++; } }
                                    ?>
                                    <tr><td colspan="<?=$cols;?>" class="text-muted py-4">No task yet for this activity. Use the + above to add the first one.</td></tr>
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
</div>
<div id="taskModal" class="modal-block modal-block-primary mfp-hide"></div>
<div id="deleteTaskModal" class="modal-block modal-block-primary mfp-hide">
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