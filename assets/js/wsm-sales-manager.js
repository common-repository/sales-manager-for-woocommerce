(function ($) {
  jQuery(document).ready(function ($) {
    /*
        setup the special form inputs (select2)
    */
    $(".wsm_tax_values").each(function () {
      $(this).attr("multiple", "multiple").select2({
        placeholder: "Select...",
        width: "50%",
      });
    });
  });

  /*
  add existing row ids to array variable to be able to manage it easier
  */
  if ($("#_wsm_filter_ids_array")[0]) {
    var rowids_array = $("#_wsm_filter_ids_array").val().split(",");
    var nextrowid = rowids_array.length;
  }

  /*
    show or hide the schedule discount button depending on the discount % selected
    */
  $("#discount").on("change", function () {
    if (this.value > 0) {
      $("#toggle_schedule").slideDown();
    } else {
      $("#toggle_schedule").slideUp();
      $("#schedule_sale_section").slideUp();
    }
  });

  $("#wsm-scheduled-filters").on("click", "._wsm_remove_row", function (e) {
    e.preventDefault();
    $(this).parent().parent().slideUp();
    $(this).parent().parent().attr("data-active", "no");
    removedrowid = $(this).parent().parent().attr("data-rowid");
    $(this).parent().parent().remove();
    var value = $("#_wsm_filter_count").val();
    value--;
    $("#_wsm_filter_count").val(value);

    $("._wsm_filter_row").first().find(".andclass").remove();

    rowids_array.splice($.inArray(removedrowid, rowids_array), 1);
    $("#_wsm_filter_ids_array").val(rowids_array.toString());
  });

  $("#wsm-scheduled-filters").on("change", "._wsm_tax", function (e) {
    var tax = e.target.value;
    var rowid = $(e.target).parent().parent().data("rowid");
    getTerms(rowid, tax);
  });

  $("#_wsm_add_row").on("click", function (e) {
    e.preventDefault();

    var taxrowshtml = "";
    $.each(ajax_object.taxs, function (key, value) {
      taxrowshtml +=
        '<option value="' +
        key +
        '">' +
        value.labels.singular_name +
        "</option>";
    });

    var countrows = $("#_wsm_filter_count").val();

    countrows++;

    var andhtml = "";
    if (countrows > 1) {
      andhtml = '<span class="andclass">AND</span> ';
    }

    $(this)
      .parent()
      .before(
        '<p id="_wsm_filter_row_' +
          nextrowid +
          ' class="_wsm_filter_row" data-active="yes" data-rowid="' +
          nextrowid +
          '">' +
          andhtml +
          "<span>" +
          '<select required class="_wsm_tax" name="_wsm_tax_' +
          nextrowid +
          '" style="width: 150px;">' +
          '<option value="" selected>Choose...</option>' +
          taxrowshtml +
          "</select>" +
          "</span> <span>" +
          '<select required name="_wsm_type_' +
          nextrowid +
          '" class="wsm_type" style="width: 100px;">' +
          '<option value="" selected>Choose...</option>' +
          '<option value="include">is</option>' +
          '<option value="exclude">is not</option>' +
          "</select>" +
          "</span> <span>" +
          '<select required name="_wsm_tax_values_' +
          nextrowid +
          '[]" id="_wsm_tax_values_' +
          nextrowid +
          '"multiple="multiple" class="wsm_tax_values">' +
          '<option value=""  selected>Select...</option>' +
          "</select></span>" +
          '<span class="wsm_remove">' +
          '<button id="_wsm_remove_row_' +
          nextrowid +
          '" class="_wsm_remove_row button primary">Remove</button>' +
          "</span>" +
          "</p>"
      );
    //$(this).parent().before("<hr/>");
    $("#_wsm_filter_count").val(countrows);

    $('p[data-rowid="' + nextrowid + '"]')
      .find("#_wsm_tax_values_" + nextrowid)
      .attr("multiple", "multiple")
      .select2({
        placeholder: "Select...",
        width: "50%",
      });

    rowids_array.push(nextrowid);
    var rowids_array_string = rowids_array.toString();
    if (rowids_array_string.charAt(0) == ",") {
      rowids_array_string = rowids_array_string.substring(1);
    }
    $("#_wsm_filter_ids_array").val(rowids_array_string);

    nextrowid++;
  });

  /*
    Get taxonomy terms for the selected taxonomy
    */
  var getTerms = function (row_id, tax_name) {
    jQuery.ajax({
      type: "POST",
      data: {
        action: "wsm_get_tax_terms",
        rowid: row_id,
        tax: tax_name,
        ajax_nonce: ajax_object.nonce,
      },
      url: ajaxurl,
      dataType: "json",
      success: function (response) {
        console.log(response + "success");
        updateRow(response);
      },
      error: function (response) {
        console.log(response + "error failed");
      },
    });
  };

  /*
    Update filter row with correct taxonomy terms when response from ajax is received
    */
  var updateRow = function (response) {
    var htmlstring = "";
    responseterms = response.terms;

    $.each(responseterms, function (key, value) {
      htmlstring += '<option value="' + key + '">' + value + "</option>";
    });

    $('p[data-rowid="' + response.rowid + '"]')
      .find("#_wsm_tax_values_" + response.rowid)
      .html(htmlstring);
  };



  /*
    Generic functions to bulk select or unselect any type of taxonomy elements
    */
  $(".selectAll").on("click", function (e) {
    e.preventDefault();
    selectAll($(this).data("slug"));
  });

  $(".selectNo").on("click", function (e) {
    e.preventDefault();
    selectNo($(this).data("slug"));
  });

  var selectAll = function (tax) {
    var selectedItems = [];
    var allOptions = $("#wsm_" + tax + " option");
    allOptions.each(function () {
      selectedItems.push($(this).val());
    });
    $("#wsm_" + tax)
      .val(selectedItems)
      .trigger("change");
  };

  var selectNo = function (tax) {
    var selectedItems = [];
    $("#wsm_" + tax)
      .val("")
      .change();
  };

  /*
    dynamic searchable input for products to ignore, with ajax call
    */
  $("#ignore_ids_select2").select2({
    //width: 'style', // need to override the changed default
    placeholder: "Select products",
    ajax: {
      url: ajaxurl,
      dataType: "json",
      data: function (params) {
        var query = {
          search: params.term,
          action: "search_ignore_products",
          ajax_nonce: ajax_object.nonce,
        };
        return query;
      },
      processResults: function (data) {
        var options = [];
        console.log(data);

        if (data) {
          // data is the array of arrays, and each of them contains ID and the Label of the option
          $.each(data, function (index, txt) {
            // do not forget that "index" is just auto incremented value
            options.push({ id: index, text: txt });
          });
        }

        return {
          results: options,
        };
      },
      //cache: true,
      delay: 250, // wait 250 milliseconds before triggering the request
    },
    minimumInputLength: 3, // the minimum of symbols to input before perform a search
  });
})(jQuery);
