var me = this;
im.bind('filterFullyLoaded',function(e){
	if (e.eventData.im.namespace === me.im.namespace){
		//This is me, start initialization.
	}
});
im.bind('filterChange',function(e){
  if (e.eventData.im.namespace === me.im.namespace) {
    im.showLoader('Applying Sepia');

    setTimeout(function(){
      // Just apply, there is no variation.

      im.activeElement.setFilter(im.filter.sepia);
      im.activeElement.applyFilter();

      im.hideLoader();
      im.fire('SepiaFilterDidFinish');
      im.fire('filterApplied', me);
      // Apply Filter
    }, 10); // Allow loader to show
  }
});
im.bind('filterApplyExample',function(e){
	if (e.eventData.namespace === me.namespace) {
		console.log(e.eventData);
		e.eventData.image.setFilter(im.filter.sepia);
		im.fire('filterBuiltExample', me, e.eventData.elem);
	}
});
