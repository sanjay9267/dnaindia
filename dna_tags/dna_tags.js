(function ($) {
  Drupal.behaviors.dnaTags = {
    attach: function (context, settings) {
      $('#edit-dna-tags-submit').easyconfirm({locale: { title: 'Are you sure ?',
	text: 'After update it, you can not get previous data', button: ['Cancel','Confirm']}});
 
    }
  };
}(jQuery));
