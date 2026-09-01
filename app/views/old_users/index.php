<section id="content" style="margin-bottom: 50px">

    <div class="content-wrap">
        <div class="container clearfix">
            <div class="row before-content">
                <div id="breadcrumbs-style" class="global-margin-from-nav col-md-12">
                    <nav aria-label="breadcrumb" role="navigation" class="top-breadcrumb-style">
                        <ol class="breadcrumb-style">
                            <li class="breadcrumb-item">
                                <a href="/<?= $this->lang ?>"><?= $this->T('breadcrumb_homepage'); ?></a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="#"><?= $this->T('breadcrumb_connect'); ?></a>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
            <h1><?= $this->T('account_title'); ?></h1>

            <div class="col_one_third nobottommargin">

                <div class="well well-lg nobottommargin" style="padding-bottom: 40px;">
                    <h3><?= $this->T('account_register_title'); ?></h3>
                    <a style="float:left" href="/<?= $this->lang?>/users/register" class="button button-small button-rounded"><?= $this->T('account_register_btn'); ?></a>
                </div>

            </div>

            <div class="col_two_third col_last nobottommargin">
                <h3><?=$this->T('account_login_title');?></h3>
                <form id="login-form" name="login-form" class="nobottommargin" action="/<?=$this->lang;?>/users/loginAction" method="post">

                    <div class="col_half">
                        <label for="login-form-username"><?= $this->T('account_email_field'); ?></label>
                        <input type="text" id="login-form-username" name="email" value="" class="form-control" />
                    </div>
                    <div class="col_half col_last">
                        <label for="login-form-password"><?= $this->T('account_password_field'); ?></label>
                        <input type="password" id="login-form-password" name="password" value="" class="form-control" />
                    </div>
                    <div class="col_full" style="display: none">
                        <input type="text" id="login-form-step" name="step" value="<?= $this->R->step ?>" class="form-control" />
                    </div>
                    <div class="col_full nobottommargin">
                        <button class="button button-3d nomargin" id="login-form-submit" name="login-form-submit" value="login"><?=$this->T('account_login_btn');?></button>
                        <a href="/<?= $this->lang?>/users/forgotPassword" class="fright"><?=$this->T('account_forgot_password_btn');?></a>
                    </div>

                </form>

            </div>

        </div>

    </div>

</section><!-- #content end -->
<script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
        {
        "@type": "ListItem",
        "position": "1",
        "name": "<?= $this->T('breadcrumb_homepage'); ?>",
        "item": "<?=  $this->S['project_url'].'/' . $this->lang . '/' ?>"
    },         {
        "@type": "ListItem",
        "position": "2",
        "name": "<?= $this->T('breadcrumb_connect'); ?>",
        "item": "<?= $this->S['project_url'] .'/' . $this->lang . '/users' ?>"
    }
        ]
    }
</script>