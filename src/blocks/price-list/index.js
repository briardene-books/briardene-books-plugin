(function (wp) {
  var registerBlockType = wp.blocks.registerBlockType;
  var __ = wp.i18n.__;
  var ServerSideRender = wp.serverSideRender;

  registerBlockType("briardene/price-list", {
    title: __("Price List", "briardene-books"),
    icon: "list-view",
    category: "common",
    edit: function () {
      return wp.element.createElement(ServerSideRender, {
        block: "briardene/price-list",
      });
    },
    save: function () {
      return null;
    },
  });
})(window.wp);
