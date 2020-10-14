var getUrlParameter = function getUrlParameter(sParam) {
	var urlParams = new URLSearchParams(window.location.search);
	return urlParams.get(sParam);
};

$( "#tabs" ).tabs();

var options = [];

$.ajax({
	url: '?action=getstations',
	method: 'GET',
	dataType: 'json',
	data: {},
	async: false,
	cache: false,
	timeout: 3 * 60 * 1000,
	error: function() {},
	success: function(res) {
		$.each(res, function (i, item) {
			options.push({
				id: item,
				title: item
			});
		});	
	}
});

var selectLastValue;
$('#combobox_start').selectize({
    valueField: 'title',
    labelField: 'title',
    searchField: 'title',
    sortField: 'title',
    options: options,
    create: false,
    maxItems: 1,
	maxOptions: 10,
	closeAfterSelect: true,
	allowEmptyOption: false,
	selectOnTab: true,
	onDropdownOpen: function($dropdown){
		selectLastValue = $('#combobox_start')[0].selectize.getValue();
		$('#combobox_start')[0].selectize.clear();
	},
	onDropdownClose: function($dropdown){
		if(!$('#combobox_start')[0].selectize.getValue()){
			$('#combobox_start')[0].selectize.setValue(selectLastValue);
		}
	}
});

$('#combobox_end').selectize({
    valueField: 'title',
    labelField: 'title',
    searchField: 'title',
    sortField: 'title',
    options: options,
    create: false,
    maxItems: 1,
	maxOptions: 10,
	closeAfterSelect: true,
	allowEmptyOption: false,
	selectOnTab: true,
	onDropdownOpen: function($dropdown){
		selectLastValue = $('#combobox_end')[0].selectize.getValue();
		$('#combobox_end')[0].selectize.clear();
	},
	onDropdownClose: function($dropdown){
		if(!$('#combobox_end')[0].selectize.getValue()){
			$('#combobox_end')[0].selectize.setValue(selectLastValue);
		}
	}
});

$('#combobox_start')[0].selectize.setValue([getUrlParameter('start')]);
$('#combobox_end')[0].selectize.setValue([getUrlParameter('end')]);


$( "input[name=searchtype]" ).checkboxradio({
	icon: false
});

var searchtype = getUrlParameter('searchtype');
if(searchtype){
	var radio = $("input[name=searchtype]");
		radio[searchtype].checked = true;
		radio.button("refresh");
}

$('#buttonsearch').click(function() {
    $(this).attr('disabled', 'disabled');
    $(this).parents('form').submit();
});
