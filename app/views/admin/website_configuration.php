<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Configuration</h2>
</header>
<div class="row">
    <div class="col">
        <form class="form-horizontal" action="<?=$this->L("admin/website_configuration_update");?>" method="post">
            <section class="card">
                <div class="card-body">
                    <div class="form-group row pb-3 pb-3">
                        <div class="col-md-10" style="width: 100%;">
                        <textarea class="form-control" rows="50" id="codemirror_code" name="content"
                                  data-plugin-codemirror
                                  data-plugin-options='{ "mode": "text/x-php", "theme":"material-palenight" }'><?=$data['content'];?></textarea>
                        </div>
                    </div>
                </div>
                <footer class="card-footer text-end">
                    <button class="submit-button btn btn-primary btn-px-4 py-3 font-weight-semibold line-height-1">Submit </button>
                    <button type="reset" class="btn btn-default btn-px-4 py-3 line-height-1">Reset</button>
                </footer>
            </section>
        </form>
    </div>
</div>