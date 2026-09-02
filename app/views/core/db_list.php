<?php
//debug($data);
$page_link_prefix = "core/db_list/" . $data['model_name'];
$suffix_terms = [];
if (isset($data['search']) && ($data['search'] != "")) {
    $suffix_terms[] = "search-term=" . $data['search'];
}
if (!empty($data['filter_data'])) {
    foreach ($data['filter_data'] as $filter_data => $value) {
        if ($value != "") {
            $suffix_terms[] = $filter_data . "=" . $value;
        }
    }
}
$page_link_suffix = (count($suffix_terms) > 0) ? "?" . implode("&", $suffix_terms) : "";
?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6"><?= display($data['meta_name']); ?></h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= (isset($data['count']))? "Total of ".display($data['count'])." entries" : "No entries yet"; ?></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <div class="datatable-header">
                        <form method="get" action="<?=$this->L($page_link_prefix);?>">

                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                                <a href="<?=$this->L("core/db_add/".display($data['model_name']));?>" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">+ Add</a>
                            </div>
                            <?php
                            $html = "";

                            if((isset($data['meta_filters']))&&(count($data['meta_filters'])>0)){
                                foreach ($data['meta_filters'] as $filter){
                                    $filter_value =
                                    $html .= filter_DropDown($filter['key'], $filter, $data['filter_data'][$filter['key']]);
                                }
                            }
                            print $html;

                            ?>
                            <div class="col-12 col-lg-auto ms-auto ml-auto ps-lg-1">
                                <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                    <div class="input-group">

                                        <input type="text" class="search-term form-control" name="search-term" id="search-term" placeholder="Search" value="<?= htmlspecialchars((string)($data['search'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button class="btn btn-default" type="submit"><i class="bx bx-search"></i></button>

                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    </div>
					<?php
					if(count($data['data'])>0){
						?>
                        <div class="table-responsive">
                            <table class="table table-ecommerce-simple table-borderless table-striped mb-0" id="datatable-list" style="min-width: 640px;">
                                <thead>
                                <tr>
                                    <th width="3%"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
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
                                    <th width="10%">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
								<?php
								$aa = (($data['page']-1)*$data['items'])+1;
								foreach ($data['data'] as $row) {
									$link = "core/db_edit/".display($data['model_name'])."/".$row['id'];
									?>
                                    <tr>
                                        <td width="30"><input type="checkbox" name="checkboxRow1" class="checkbox-style-1 p-relative top-2" value="" /></td>
                                        <td><?=$aa;?></td>
										<?php
										$first = true;
										foreach ($data['fields'] as $field => $properties) {
											if (isset($properties['appear_in_list'])) {

												$active = (isset($row['active'])) ? $row['active'] : true;
												print ($first)
													? '<td><a href="' . $this->L($link) . '"><strong>' . display_list_element($properties, $row[$field], $active) . '</strong></a></td>'
													: '<td>' . display_list_element($properties, $row[$field], $active) . '</td>';
												$first = false;
											}
										}
										?>
                                        <td>
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
                                            <a href="<?=$this->L($link);?>" ><i class='bx bxs-edit bx-sm'></i></a>
                                            <a class="modal-basic" data-id="<?=$row['id'];?>" href="#deleteModal"><i class='bx bx-trash bx-sm' ></i></a>
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
												if($start_from>=2){ print '<li class="paginate_button page-item previous disabled"><a href="#" class="page-link"><i class="bx bx-dots-horizontal-rounded" ></i></a></li>';}
												for ($page_num = $start_from; $page_num <= $end_to; $page_num++){
													$active_page = ($data['page']==$page_num) ? "active" : "";
													print '<li class="paginate_button page-item '.$active_page.'"><a href="'.$this->L($page_link_prefix."/".$page_num.$page_link_suffix).'" class="page-link">'.$page_num.'</a></li>';
												}
												if($end_to<=$last_page-1){ print '<li class="paginate_button page-item next disabled"><a href="#" class="page-link"><i class="bx bx-dots-horizontal-rounded" ></i></a></li>';}
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