<script type="text/javascript">
function initAccountsDropdown($select, $dropdownParent) {
    var options = {
        ajax: {
            url: '{{route("accounts-dropdown")}}',
            dataType: 'json',
            data: function (params) {
                return {
                    q: params.term,
                    account_primary_type: $select.data('account-primary-type')
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            }
        },
        escapeMarkup: function(markup) {
            return markup;
        },
        templateResult: function(data) {
            return data.html;
        },
        templateSelection: function(data) {
            return data.text;
        }
    };

    if ($dropdownParent) {
        options.dropdownParent = $dropdownParent;
    }

    $select.select2(options);
}

$(document).ready(function(){
    $("select.accounts-dropdown").each(function () {
        initAccountsDropdown($(this));
    });
});
$(document).on('mouseover', '.select2-selection__rendered', function(){
    $(this).removeAttr('title');
});
$(document).on('shown.bs.modal', '.modal', function(){
    var $modal = $(this);
    $modal.find('select.accounts-dropdown').each(function () {
        initAccountsDropdown($(this), $modal);
    });
});
</script>
