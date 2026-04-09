<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-papir-agent" data-toggle="tooltip" title="<?php echo $this->language->get('button_save'); ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $this->language->get('button_cancel'); ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
      </div>
      <h1><?php echo $this->language->get('heading_title'); ?></h1>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?></div>
    <?php } ?>
    <?php if ($success) { ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $success; ?></div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-plug"></i> Papir ERP Agent</h3></div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-papir-agent" class="form-horizontal">

          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo $this->language->get('entry_status'); ?></label>
            <div class="col-sm-10">
              <select name="papir_agent_status" class="form-control">
                <option value="1" <?php if ($papir_agent_status) { ?>selected<?php } ?>><?php echo $this->language->get('text_enabled'); ?></option>
                <option value="0" <?php if (!$papir_agent_status) { ?>selected<?php } ?>><?php echo $this->language->get('text_disabled'); ?></option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo $this->language->get('entry_token'); ?></label>
            <div class="col-sm-10">
              <div class="input-group">
                <input type="text" name="papir_agent_token" value="<?php echo $papir_agent_token; ?>" class="form-control" readonly onclick="this.select()" />
                <span class="input-group-btn">
                  <a href="<?php echo $regenerate; ?>" class="btn btn-warning" onclick="return confirm('<?php echo $this->language->get('text_regenerate_confirm'); ?>')">
                    <i class="fa fa-refresh"></i> <?php echo $this->language->get('button_regenerate'); ?>
                  </a>
                </span>
              </div>
              <p class="help-block"><?php echo $this->language->get('help_token'); ?></p>
            </div>
          </div>

          <div class="form-group">
            <label class="col-sm-2 control-label"><?php echo $this->language->get('entry_api_url'); ?></label>
            <div class="col-sm-10">
              <input type="text" value="<?php echo $api_url; ?>" class="form-control" readonly onclick="this.select()" />
              <p class="help-block"><?php echo $this->language->get('help_api_url'); ?></p>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>
