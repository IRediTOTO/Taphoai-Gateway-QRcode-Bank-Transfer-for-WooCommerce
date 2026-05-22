jQuery(function ($) {
  const bankSelect = $("#woocommerce_bank_notify_bank_select");
  const accountNumberInput = $("input[name=woocommerce_bank_notify_bank_account_number]");
  const webhookApiKeyInput = $("#woocommerce_bank_notify_webhook_api_key");
  const paymentCodeModeSelect = $("#woocommerce_bank_notify_payment_code_mode");

  function setRequired(fields, isRequired) {
    fields.prop("required", isRequired);

    if (isRequired) {
      fields.attr("required", "required").attr("aria-required", "true");
      return;
    }

    fields.removeAttr("required aria-required");
  }

  function togglePaymentCodeFields() {
    if (!paymentCodeModeSelect.length) {
      return;
    }

    const mode = paymentCodeModeSelect.val();
    const modeRow = $(".payment-code-mode-field").closest("tr");
    const prefixField = $(".payment-code-prefix-field");
    const expiryFields = $(".payment-code-expiry-field");

    modeRow.find(".payment-code-natural-notice").remove();

    if (mode === "natural") {
      setRequired(prefixField, false);
      prefixField.closest("tr").hide();
      setRequired(expiryFields, true);

      const manageUrl = "admin.php?page=bank-notify-payment-codes&tab=import";
      const notice = $("<p>", {
        class: "payment-code-natural-notice",
      })
        .css({
          marginTop: "10px",
          padding: "10px",
          background: "#e7f3ff",
          borderLeft: "4px solid #2271b1",
          borderRadius: "4px",
        })
        .append(
          $("<strong>").text("Chế độ chuỗi tự nhiên: "),
          document.createTextNode("Mã thanh toán sẽ được lấy từ pool mã đã import. "),
          $("<a>", {
            href: manageUrl,
            text: "Quản lý mã thanh toán",
          }).css({
            fontWeight: 600,
            textDecoration: "none",
          })
        );

      modeRow.find("td.forminp").append(notice);
      return;
    }

    setRequired(prefixField, true);
    prefixField.closest("tr").show();
    setRequired(expiryFields, false);
  }

  togglePaymentCodeFields();
  paymentCodeModeSelect.on("change", togglePaymentCodeFields);

  if (bankSelect.length && accountNumberInput.length) {
    function enhanceBankSelectSearch() {
    if ($.fn.selectWoo) {
      if (!bankSelect.data("select2")) {
        bankSelect.selectWoo({
          allowClear: true,
          placeholder: bankSelect.data("placeholder") || "Chọn hoặc tìm ngân hàng",
          width: "resolve",
        });
      }

      return;
    }

    if ($("#bank-notify-bank-search").length) {
      return;
    }

    const originalOptions = bankSelect
      .find("option")
      .map(function () {
        const option = $(this);
        return {
          value: option.attr("value"),
          text: option.text(),
          selected: option.is(":selected"),
        };
      })
      .get();

    const searchInput = $("<input>", {
      type: "search",
      id: "bank-notify-bank-search",
      class: "regular-input bank-notify-bank-search",
      placeholder: "Tìm ngân hàng...",
      "aria-label": "Tìm ngân hàng",
    });

    bankSelect.before(searchInput);

    function renderFilteredOptions(query) {
      const selectedValue = bankSelect.val();
      const normalizedQuery = query.trim().toLowerCase();
      const matches = originalOptions.filter(function (option) {
        return (
          option.value === selectedValue ||
          !normalizedQuery ||
          option.text.toLowerCase().includes(normalizedQuery)
        );
      });

      bankSelect.empty();

      matches.forEach(function (option) {
        bankSelect.append(
          $("<option>", {
            value: option.value,
            text: option.text,
            selected: option.value === selectedValue,
          })
        );
      });
    }

    searchInput.on("input", function () {
      renderFilteredOptions($(this).val());
    });
  }

  enhanceBankSelectSearch();

  const helpText = $("<div>", {
    class: "bank-notify-account-help",
  }).css({
    boxSizing: "border-box",
    color: "#856404",
    backgroundColor: "#fff3cd",
    borderColor: "#ffeeba",
    padding: ".75rem 1.25rem",
    borderRadius: ".25rem",
    border: "1px solid transparent",
    marginTop: ".5rem",
    maxWidth: "400px",
  });

  accountNumberInput.parent().find(".bank-notify-account-help").remove();
  accountNumberInput.parent().append(helpText);

  function updateAccountNumberFieldUi() {
    const bank = bankSelect.val();
    const vaRequiredBanks = ["bidv", "ocb", "msb", "kienlongbank"];

    if (vaRequiredBanks.includes(bank)) {
      $("label[for=woocommerce_bank_notify_bank_account_number]").text("Số VA");
      helpText.html("Vui lòng điền chính xác <strong>số VA</strong> để nhận được biến động giao dịch.");
      return;
    }

    $("label[for=woocommerce_bank_notify_bank_account_number]").text("Số tài khoản");
    helpText.html("Vui lòng điền chính xác <strong>số tài khoản ngân hàng</strong> để nhận được biến động giao dịch.");
  }

  updateAccountNumberFieldUi();
    bankSelect.on("change", updateAccountNumberFieldUi);
  }

  if (webhookApiKeyInput.length && !$("#bank-notify-generate-api-key").length) {
    const generateButton = $("<button>", {
      type: "button",
      id: "bank-notify-generate-api-key",
      class: "button bank-notify-generate-api-key",
      text: "Generate API key",
    });

    webhookApiKeyInput.after(generateButton);

    generateButton.on("click", function () {
      const bytes = new Uint8Array(32);
      window.crypto.getRandomValues(bytes);

      const hex = Array.from(bytes)
        .map((byte) => byte.toString(16).padStart(2, "0"))
        .join("");

      webhookApiKeyInput.val("bnk_" + hex).trigger("change");
    });
  }

  let logoMediaUploader = null;
  let currentLogoWrapper = null;

  $(document).on("click", ".bank-notify-upload-logo-button", function (event) {
    event.preventDefault();

    const button = $(this);
    currentLogoWrapper = button.closest(".bank-notify-logo-upload-wrapper");

    if (!window.wp || !window.wp.media) {
      return;
    }

    if (!logoMediaUploader) {
      logoMediaUploader = window.wp.media({
        title: "Chọn Logo",
        button: {
          text: "Sử dụng ảnh này",
        },
        multiple: false,
        library: {
          type: "image",
        },
      });

      logoMediaUploader.on("select", function () {
        if (!currentLogoWrapper) {
          return;
        }

        const attachment = logoMediaUploader.state().get("selection").first().toJSON();
        const inputField = currentLogoWrapper.find("input[type=hidden]");
        const previewImage = currentLogoWrapper.find(".bank-notify-logo-preview img");
        const uploadButton = currentLogoWrapper.find(".bank-notify-upload-logo-button");
        const removeButton = currentLogoWrapper.find(".bank-notify-remove-logo-button");

        inputField.val(attachment.url).trigger("change");
        previewImage.attr("src", attachment.url).show();
        uploadButton.text("Thay đổi Logo");
        removeButton.show();
      });
    }

    logoMediaUploader.open();
  });

  $(document).on("click", ".bank-notify-remove-logo-button", function (event) {
    event.preventDefault();

    const button = $(this);
    const wrapper = button.closest(".bank-notify-logo-upload-wrapper");
    const inputField = wrapper.find("input[type=hidden]");
    const previewImage = wrapper.find(".bank-notify-logo-preview img");
    const uploadButton = wrapper.find(".bank-notify-upload-logo-button");

    inputField.val("").trigger("change");
    previewImage.attr("src", "").hide();
    uploadButton.text("Chọn Logo");
    button.hide();
  });
});
