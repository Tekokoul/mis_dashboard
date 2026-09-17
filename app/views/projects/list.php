<?php
//debug($data);
$page_link_prefix = "projects/list";
$suffix_terms = [];
if (isset($data['search']) && ($data['search'] != "")) {
    $suffix_terms['search-term'] = (string)$data['search'];
}
if (!empty($data['filter_data'])) {
    foreach ($data['filter_data'] as $filter_data => $value) {
        if ($value != "") {
            $suffix_terms[(string)$filter_data] = (string)$value;
        }
    }
}
$page_link_suffix = (count($suffix_terms) > 0)
    ? htmlspecialchars("?" . http_build_query($suffix_terms), ENT_QUOTES, 'UTF-8')
    : "";
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= display($data['meta_name']); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= (isset($data['count']))? "Total of ".display($data['count'])." entries" : "No entries yet"; ?></span></li>
        </ol>
    </div>
</header>
<?php // Back from the form after its Delete: say what went, once. ?>
<?php if (is_string($_GET['deleted'] ?? null) && $_GET['deleted'] !== '') {
    $goneT = (int)($_GET['tasks'] ?? 0); $goneD = (int)($_GET['deliveries'] ?? 0); ?>
<div class="afcdc-review-panel afcdc-merge-panel" role="status"><div class="afcdc-review__note"><span class="afcdc-review__tag">Deleted</span> Activity <strong><?= display($_GET['deleted']); ?></strong> is gone, with its <?= $goneT; ?> task<?= $goneT === 1 ? '' : 's'; ?> and <?= $goneD; ?> delivery record<?= $goneD === 1 ? '' : 's'; ?>.</div></div>
<?php } ?>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <div class="datatable-header afcdc-sticky">
                        <form method="get" action="<?=$this->L($page_link_prefix);?>">

                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-lg-auto mb-3 mb-lg-0 afcdc-add-col">
                                <?php if (can_edit()): ?><a href="<?=$this->L("projects/add");?>" class="btn btn-primary afcdc-add btn-md font-weight-semibold btn-py-2 px-4">+ Add</a><?php endif; ?>
                                <?php // Appears once two or more rows are ticked (custom.js); opens the merge page with them. ?>
                                <?php if (can_vet() && merge_available($this->DB)): ?><a href="#" class="btn btn-light border btn-md btn-py-2 px-3 afcdc-merge" data-afcdc-merge="<?= $this->L('projects/merge'); ?>" hidden>Merge selected</a><?php endif; ?>
                            </div>
                            <?php
                            // The search box, with every filter in a panel under it (list_builder.php list_toolbar).
                            print '<div class="col-12 col-lg mb-3 mb-lg-0">'
                                . list_toolbar((array)($data['meta_filters'] ?? []), (array)($data['filter_data'] ?? []), $data['search'] ?? '', $this->L($page_link_prefix), 'pm_projects', 'projects/edit',
                                    // While moves are pending, one control accepts everything left after the person has looked.
                        (can_vet() && allocation_pending_count($this->DB) > 0) ? '<a href="#" class="btn btn-sm btn-light border afcdc-review__all" data-review-action="accept_all">Accept all pending</a>' : '')
                                . '</div>';
                            ?>

                            
                        </div>
                        </form>
                    </div>
                    <?php
                    if(count($data['data'])>0){
                        ?>
                        <div class="table-responsive">
                            <table class="table table-ecommerce-simple table-borderless table-striped mb-0" id="datatable-list" style="min-width: 640px;">
                                <thead>
                                <tr>
                                    <th width="3%" class="afcdc-col-check"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
                                    <th width="4%" class="afcdc-col-num">#</th>
                                    <?php
                                    foreach ($data['fields'] as $field => $properties){
                                        if(isset($properties['appear_in_list'])){
                                            $title = (isset($properties['title'])) ? $properties['title'] : $field;
                                            ?>
                                            <th width="<?=$properties['list_width'];?>%" class="afcdc-col-<?= preg_replace('/[^a-z0-9_]/i', '', $field); ?>"><?=ucfirst($title)?></th>
                                            <?php
                                            // Beside the name: how many tasks, and a square in the status colour.
                                            if ($field === 'name') { ?><th width="7%" class="afcdc-col-tasks" title="How many tasks the activity is delivered through, and its status: green completed, orange in progress, red not started">Tasks</th><?php }
                                        }
                                    }
                                    ?>
                                    <th width="10%" class="afcdc-col-actions">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $aa = (($data['page']-1)*$data['items'])+1;
                                // The Tasks column: each row's task count (one query for the page) and its status as the graphs count it (library: delivery_rollup).
                                $statusRoll = delivery_rollup($this->DB)['activity'];
                                $taskCounts = [];
                                $pageIds = array_values(array_filter(array_map('intval', array_column((array)$data['data'], 'id'))));
                                if ($pageIds) {
                                    foreach ((array)$this->DB->MQ("SELECT project_id, COUNT(*) AS n FROM pm_projects_tasks_tbl WHERE project_id IN (" . implode(',', array_fill(0, count($pageIds), '?')) . ") GROUP BY project_id", "all", $pageIds) as $tc) {
                                        $taskCounts[(int)$tc['project_id']] = (int)$tc['n'];
                                    }
                                }
                                foreach ($data['data'] as $row) {
                                    // A person who may edit opens the form; anyone else, the activity's page.
                                    $link = can_edit() ? "projects/edit/".$row['id'] : "projects_graphs/project/".$row['id'];
                                    ?>
                                    <?php $review = $data['reviews'][(int)$row['id']] ?? null; $gaps = $data['gaps'][(int)$row['id']] ?? []; $trClass = trim(((isset($row['active']) && (string)$row['active'] === '0') ? 'afcdc-row--inactive ' : '') . ($gaps ? 'afcdc-gap ' : '') . (allocation_review_visible($review) ? 'afcdc-review afcdc-review--' . display($review['status']) . ' afcdc-review--' . display($review['confidence']) : '')); ?>
                                    <tr<?= $trClass !== '' ? ' class="' . $trClass . '"' : ''; ?>>
                                        <td width="30" class="afcdc-col-check"><input type="checkbox" name="checkboxRow1" class="checkbox-style-1 p-relative top-2" value="<?= (int)$row['id']; ?>" aria-label="Select <?= display(($row['abbr'] ?? '') . ' ' . ($row['name'] ?? '')); ?>" /></td>
                                        <td class="afcdc-col-num"><?=$aa;?></td>
                                        <?php
                                        $first = true;
                                        foreach ($data['fields'] as $field => $properties) {
                                            if (isset($properties['appear_in_list'])) {

                                                $active = (isset($row['active'])) ? $row['active'] : true;
                                                $cell = display_list_element($properties, $row[$field], $active);
                                                $attrs = list_cell_attrs($field, $properties, $cell);
                                                // The name column stops at two lines (CSS .afcdc-clamp); the full text is the cell's title and the edit page.
                                                $inner = (strpos($attrs, 'afcdc-cell-name') !== false) ? '<span class="afcdc-clamp">' . $cell . '</span>' : $cell;
                                                // A moved activity carries its vetting note under the name.
                                                if ($field === 'name' && !empty($review)) { $inner .= allocation_review_note($review); }
                                                // An activity with something missing, or a broken goal / objective / programme chain, says so.
                                                if ($field === 'name' && $gaps) { $inner .= activity_gap_note($gaps); }
                                                if ($field === 'abbr' && $gaps) { $inner = activity_flag($gaps) . ' ' . $inner; }
                                                // Found through its description? Show the passage, so the row explains itself.
                                                if ($field === 'name' && ($data['search'] ?? '') !== '') { $inner .= search_match_note($row, $data['search']) . search_hits_note($row, $data['search']); }
                                                print ($first)
                                                    ? '<td' . $attrs . '><a href="' . $this->L($link) . '"><strong>' . $inner . '</strong></a></td>'
                                                    : '<td' . $attrs . '>' . $inner . '</td>';
                                                if ($field === 'name') {
                                                    $n = $taskCounts[(int)$row['id']] ?? 0;
                                                    print '<td class="afcdc-col-tasks"><span class="afcdc-tasks-n" title="' . $n . ' task' . ($n === 1 ? '' : 's') . '">' . $n . '</span>'
                                                        . delivery_status_square(delivery_rollup_status($statusRoll[(int)$row['id']] ?? null)) . '</td>';
                                                }
                                                $first = false;
                                            }
                                        }
                                        ?>
                                        <td class="afcdc-col-actions">
                                            <?php
                                            foreach ($data['meta_actions'] as $action){
                                                $show_action = false;
                                                if(isset($action['condition'])) {
                                                    if (ce_compare_values($row[$action['condition']['field']], $action['condition']['operator'], $action['condition']['value'])) {
                                                        $show_action = true;
                                                    }
                                                } else {
                                                    $show_action = true;
                                                }
                                                if($show_action){
                                                    print '<a href="' . $this->model->dynamic_link($action['link'], $row) . '" target="' . $action['target'] . '" alt="' . $action['title'] . '"><i class="bx ' . $action['icon'] . ' bx-sm"></i></a> ';
                                                }
                                            }
                                            ?>
                                            <?php if (can_record()): ?>
                                            <a href="<?=$this->L("projects/progress_edit/".(int)$row['id']);?>" aria-label="Record delivery" title="Record delivery"><i class='bx bx-list-check bx-sm' aria-hidden="true"></i></a>
                                            <?php endif; ?>
                                            <?php if (can_edit()): ?><a href="<?=$this->L($link);?>" aria-label="Edit"><i class='bx bxs-edit bx-sm' aria-hidden="true"></i></a><?php endif; ?>
                                            <?php if (can_delete()): ?><a class="modal-basic" data-id="<?=$row['id'];?>" href="#deleteModal" aria-label="Delete"><i class='bx bx-trash bx-sm' aria-hidden="true"></i></a><?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                    $aa++;
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <hr class="solid mt-5 opacity-4">
                        <div class="datatable-footer">
                            <div class="row align-items-center justify-content-between mt-3">
                                <!--                            <div class="col-md-auto order-1 mb-3 mb-lg-0">-->
                                <!--                                <div class="d-flex align-items-stretch">-->
                                <!--                                    <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">-->
                                <!--                                        <select class="form-control select-style-1 bulk-action" name="bulk-action" style="min-width: 170px;">-->
                                <!--                                            <option value="" selected>Bulk Actions</option>-->
                                <!--                                            <option value="delete">Delete</option>-->
                                <!--                                        </select>-->
                                <!--                                        <a href="ecommerce-orders-detail.html" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3">Apply</a>-->
                                <!--                                    </div>-->
                                <!--                                </div>-->
                                <!--                            </div>-->
                                <div class="col-lg-auto text-center order-3 order-lg-2">
                                    <div class="results-info-wrapper">Showing <?=(($data['page']-1)*$data['items'])+1;?> to <?=($aa-1);?> of <?=$data['count'];?> entries
                                    </div>
                                </div>
                                <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                    <div class="pagination-wrapper">
                                        <div class="dataTables_paginate paging_simple_numbers" id="datatable-list_paginate">
                                            <ul class="pagination pagination-modern pagination-modern-spacing justify-content-center">
                                                <?php
                                                $last_page = ceil($data['count']/$data['items']);
                                                $previous_disabled = ($data['page']==1) ? "disabled" : "";
                                                $next_disabled = ($data['page']==$last_page) ? "disabled" : "";
                                                $start_from = (($data['page']-2)<1) ? 1 : $data['page']-2;
                                                $end_to = (($data['page']+2)>$last_page) ? $last_page : $data['page']+2;
                                                ?>
                                                <li class="paginate_button page-item previous <?=$previous_disabled;?>"><a href="<?=$this->L($page_link_prefix.$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevrons-left' ></i></a></li>
                                                <li class="paginate_button page-item previous <?=$previous_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".($data['page']-1).$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevron-left' ></i></a></li>
                                                <?php
                                                // The dots open a small box to type a page number (custom.js, data-afcdc-jump).
                                                $dots = '<li class="paginate_button page-item afcdc-jump"><a href="#" class="page-link" title="Go to a page" aria-label="Go to a page" data-afcdc-jump="' . $this->L($page_link_prefix . '/__PAGE__' . $page_link_suffix) . '" data-afcdc-last="' . (int)$last_page . '" data-afcdc-page="' . (int)$data['page'] . '"><i class="bx bx-dots-horizontal-rounded"></i></a></li>';
                                                if($start_from>=2){ print $dots; }
                                                for ($page_num = $start_from; $page_num <= $end_to; $page_num++){
                                                    $active_page = ($data['page']==$page_num) ? "active" : "";
                                                    print '<li class="paginate_button page-item '.$active_page.'"><a href="'.$this->L($page_link_prefix."/".$page_num.$page_link_suffix).'" class="page-link">'.$page_num.'</a></li>';
                                                }
                                                if($end_to<=$last_page-1){ print $dots; }
                                                ?>
                                                <li class="paginate_button page-item next <?=$next_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".($data['page']+1).$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevron-right' ></i></a></li>
                                                <li class="paginate_button page-item next <?=$next_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".$last_page.$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevrons-right' ></i></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="deleteModal" class="modal-block modal-block-primary mfp-hide" data-tablename="<?=display($data['model_name'])?>">
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
                    <p class="mb-0">This activity will be deleted with its tasks and its delivery records. This cannot be undone.</p>
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