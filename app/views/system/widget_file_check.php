<div class="row">
    <div class="col-lg-6 col-xl-12 pt-xl-2 mt-xl-4">
        <section class="card card-featured-left card-featured-primary mb-3">
            <div class="card-body">
                <div class="widget-summary">
                    <div class="widget-summary-col widget-summary-col-icon">
                        <div class="summary-icon bg-primary">
                            <i class="fas fa-file"></i>
                        </div>
                    </div>
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title"><?=$data['settings']['file_check']['title']?> last update:</h4>
                            <div class="info pt-3">
                                <strong class="amount"><?=date ("d/m/Y @ H:i:s", filemtime(_APP_PATH.$data['settings']['file_check']['filename']))?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>