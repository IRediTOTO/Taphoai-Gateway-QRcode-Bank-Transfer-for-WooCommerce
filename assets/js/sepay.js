let pay_status = 'Unpaid';
let account_number = bank_notify_vars.account_number;
let remark = bank_notify_vars.remark;
let amount = bank_notify_vars.amount;
let order_nonce = bank_notify_vars.order_nonce;
let order_key = bank_notify_vars.order_key;
let download_mode = bank_notify_vars.download_mode;
let success_message = bank_notify_vars.success_message;
let expiry_enabled = bank_notify_vars.expiry_enabled;
let expiry_timestamp = bank_notify_vars.expiry_timestamp;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char];
    });
}

function safeDownloadUrl(url) {
    try {
        const parsedUrl = new URL(String(url || ''), window.location.href);
        return ['http:', 'https:'].includes(parsedUrl.protocol) ? parsedUrl.href : '';
    } catch (error) {
        return '';
    }
}

function safeDownloadDomId(value) {
    const id = String(value || '').replace(/[^A-Za-z0-9_-]/g, '-');
    return `bank-notify-download-${id || 'item'}`;
}

function normalizeDownload(download) {
    const remaining = download.downloads_remaining === ''
        ? ''
        : Number.parseInt(download.downloads_remaining, 10);

    return {
        id: String(download.id || ''),
        dom_id: safeDownloadDomId(download.id),
        name: String(download.name || ''),
        product_name: String(download.product_name || ''),
        download_url: safeDownloadUrl(download.download_url),
        downloads_remaining: remaining === '' ? '' : (Number.isNaN(remaining) ? 0 : Math.max(remaining, 0)),
        access_expires: String(download.access_expires || ''),
    };
}

// Countdown timer function
function startCountdown() {
    if (!expiry_enabled || !expiry_timestamp || expiry_timestamp <= 0) {
        return;
    }

    const countdownElement = document.querySelector('.countdown-time');
    if (!countdownElement) {
        return;
    }

    function updateCountdown() {
        const now = Math.floor(Date.now() / 1000);
        const timeLeft = expiry_timestamp - now;

        if (timeLeft <= 0) {
            countdownElement.textContent = 'Hết hạn';
            countdownElement.parentElement.parentElement.classList.add('expired');
            return;
        }

        const hours = Math.floor(timeLeft / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;

        countdownElement.textContent =
            String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        // Change color when less than 5 minutes
        if (timeLeft < 300) {
            countdownElement.parentElement.parentElement.classList.add('warning');
        }

        // Change color when less than 1 minute
        if (timeLeft < 60) {
            countdownElement.parentElement.parentElement.classList.add('danger');
        }
    }

    // Update immediately
    updateCountdown();

    // Update every second
    setInterval(updateCountdown, 1000);
}


function check_invoice_status() {
    jQuery.ajax({
        url: bank_notify_vars.ajax_url,
        type: 'POST',
        data: {
            order_nonce: order_nonce,
            action: 'bank_notify_check_order_status',
            orderID: bank_notify_vars.order_id,
            orderKey: order_key,
        },
        dataType: 'JSON',
        success: function (data) {
            pay_status = 'Unpaid';
            if (data.status === true && (data.order_status === 'processing' || data.order_status === 'completed')) {
                var div_paid_message = document.createElement('div');
                div_paid_message.innerHTML = `
                    <div class="paid-notification">
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                            <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
                            <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "/>
                        </svg>
                        ${success_message}
                    </div>
                `;

                jQuery('.sepay-message').append(div_paid_message);
                pay_status = 'Paid';

                jQuery('.sepay-pay-info').hide();
                jQuery('.sepay-pay-footer').hide();

                if (!Array.isArray(data.downloads) || data.downloads.length === 0) return;

                jQuery('.sepay-download').show();

                let availableDownloads = data.downloads
                    .map(normalizeDownload)
                    .filter((download) => download.download_url && (download.downloads_remaining === '' || download.downloads_remaining > 0));

                if (download_mode === 'auto') {
                    let interval;
                    let downloadCount = 0;

                    function download_multiple(urls) {
                        if (urls.length === 0) {
                            clearInterval(interval);
                            return;
                        }

                        let url = urls.pop();
                        let downloadItemIndex = availableDownloads.findIndex((download) => download.download_url === url);

                        if (downloadItemIndex < 0) return;

                        if (!(availableDownloads[downloadItemIndex].downloads_remaining === '' || availableDownloads[downloadItemIndex].downloads_remaining >= 1)) {
                            availableDownloads.splice(downloadItemIndex, 1);
                            return;
                        }

                        let downloadFrame = document.createElement('iframe');
                        downloadFrame.style.display = 'none';
                        document.body.appendChild(downloadFrame);

                        try {
                            downloadFrame.contentWindow.location.href = url;
                            downloadCount++;

                            let countdownElem = document.querySelector('.sepay-download .countdown');
                            if (countdownElem) {
                                countdownElem.textContent = `Đã bắt đầu tải ${downloadCount} tệp. Vui lòng kiểm tra thư mục tải xuống...`;
                            }

                            setTimeout(() => {
                                document.body.removeChild(downloadFrame);
                            }, 2000);
                        } catch (e) {
                            console.error('Download error:', e);
                            document.body.removeChild(downloadFrame);
                        }

                        if (availableDownloads[downloadItemIndex].downloads_remaining > 0) {
                            availableDownloads[downloadItemIndex].downloads_remaining = availableDownloads[downloadItemIndex].downloads_remaining - 1;
                        }
                    }

                    setTimeout(() => {
                        let urls = availableDownloads.map((download) => download.download_url).filter(Boolean);

                        if (urls.length === 0) {
                            alert('Toàn bộ lượt tải xuống đã hết, vui lòng kiểm tra chi tiết đơn hàng và mục tải xuống.');
                            return;
                        }

                        let countdownElem = document.querySelector('.sepay-download .countdown');
                        if (countdownElem) {
                            countdownElem.textContent = `Bắt đầu tải xuống ${urls.length} tệp...`;
                        }

                        interval = setInterval(() => download_multiple(urls), 1000);
                    }, 2000);

                    jQuery('.force-download').on('click', () => {
                        let urls = availableDownloads.map((download) => download.download_url).filter(Boolean);

                        if (urls.length === 0) {
                            alert('Toàn bộ lượt tải xuống đã hết, vui lòng kiểm tra chi tiết đơn hàng và mục tải xuống.');
                            return;
                        }

                        clearInterval(interval);
                        downloadCount = 0;

                        urls.forEach((url) => {
                            let a = document.createElement('a');
                            a.href = url;
                            a.download = '';
                            a.target = '_blank';
                            a.style.display = 'none';
                            document.body.appendChild(a);
                            a.click();
                            setTimeout(() => {
                                document.body.removeChild(a);
                            }, 100);
                            downloadCount++;
                        });

                        let countdownElem = document.querySelector('.sepay-download .countdown');
                        if (countdownElem) {
                            countdownElem.textContent = `Đã bắt đầu tải ${downloadCount} tệp. Vui lòng kiểm tra thư mục tải xuống...`;
                        }
                    });
                }

                if (download_mode === 'manual') {
                    const downloadGroups = [...new Set(availableDownloads.map((download) => download.product_name))];

                    function formatDate(value) {
                        const date = new Date(value);
                        if (Number.isNaN(date.getTime())) {
                            return '∞';
                        }

                        let year = new Intl.DateTimeFormat('vi', { year: 'numeric' }).format(date);
                        let month = new Intl.DateTimeFormat('vi', { month: '2-digit' }).format(date);
                        let day = new Intl.DateTimeFormat('vi', { day: '2-digit' }).format(date);

                        return `${day}/${month}/${year}`;
                    }

                    function decrementDownloadRemaining(downloadId) {
                        let downloadItemIndex = availableDownloads.findIndex((download) => download.dom_id === downloadId);
                        const row = document.getElementById(downloadId);

                        if (downloadItemIndex < 0) return;

                        if (availableDownloads[downloadItemIndex].downloads_remaining !== '' && availableDownloads[downloadItemIndex].downloads_remaining < 1) {
                            availableDownloads.splice(downloadItemIndex, 1);
                            if (row) {
                                jQuery(row).find('.download-button').removeAttr('href').attr('disabled', true);
                            }
                            return;
                        }

                        if (availableDownloads[downloadItemIndex].downloads_remaining > 0) {
                            availableDownloads[downloadItemIndex].downloads_remaining = availableDownloads[downloadItemIndex].downloads_remaining - 1;
                            if (row) {
                                jQuery(row).find('.remaining').text(availableDownloads[downloadItemIndex].downloads_remaining);
                            }
                        }
                    }

                    jQuery(document).on('click', '.download-item .download-button', (event) => {
                        const downloadId = jQuery(event.currentTarget).attr('download-id');

                        decrementDownloadRemaining(downloadId);
                    });

                    downloadGroups.forEach((group) => {
                        let downloadItemsHtml = '';

                        availableDownloads
                            .filter((download) => download.product_name === group)
                            .forEach((download) => {
                                downloadItemsHtml += `
                                <div class="download-item" id="${escapeHtml(download.dom_id)}">
                                    <div class="download-info">
                                        <p class="download-name">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                            ${escapeHtml(download.name)}
                                        </p>
                                        <div>
                                            <p class="download-remaining">Lượt tải còn lại: <span class="remaining">${
                                                download.downloads_remaining !== '' ? escapeHtml(download.downloads_remaining) : '∞'
                                            }</span></p>
                                            <p class="download-expire">Hết hạn: ${download.access_expires ? escapeHtml(formatDate(download.access_expires)) : '∞'}</p>
                                        </div>
                                    </div>
                                    <a href="${escapeHtml(download.download_url)}" class="download-button" download-id="${escapeHtml(download.dom_id)}" download-url="${escapeHtml(download.download_url)}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="lucide lucide-download">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="7 10 12 15 17 10" />
                                            <line x1="12" x2="12" y1="15" y2="3" />
                                        </svg>
                                        Tải xuống
                                    </a>
                                </div>
                            `;
                            });

                        jQuery('.sepay-download .download-list').append(`
                            <div class="download-group">
                                ${escapeHtml(group)}
                            </div>
                            ${downloadItemsHtml}
                        `);
                    });
                }
            }
        },
    });
}

setInterval(function () {
    if (pay_status === 'Unpaid') {
        check_invoice_status();
    }
}, 5000);

document.addEventListener('DOMContentLoaded', function () {
    // Start countdown timer
    startCountdown();

    // Copy account number
    const copyAccountBtn = document.getElementById("sepay_copy_account_number_btn");
    if (copyAccountBtn) {
        copyAccountBtn.addEventListener("click", function (e) {
            e.preventDefault();
            navigator.clipboard.writeText(account_number).then(() => {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"  class="bi bi-check2" viewBox="0 0 16 16">  <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#4bbf73"></path></svg>';
                setTimeout(() => {
                    this.innerHTML = '<svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path></svg>';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy account number:', err);
            });
        });
    }

    // Copy amount
    const copyAmountBtn = document.getElementById("sepay_copy_amount_btn");
    if (copyAmountBtn) {
        copyAmountBtn.addEventListener("click", function (e) {
            e.preventDefault();
            navigator.clipboard.writeText(amount).then(() => {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"  class="bi bi-check2" viewBox="0 0 16 16">  <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#4bbf73"></path></svg>';
                setTimeout(() => {
                    this.innerHTML = '<svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path></svg>';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy amount:', err);
            });
        });
    }

    // Copy transfer content
    const copyTransferBtn = document.getElementById("sepay_copy_transfer_content_btn");
    if (copyTransferBtn) {
        copyTransferBtn.addEventListener("click", function (e) {
            e.preventDefault();
            navigator.clipboard.writeText(remark).then(() => {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"  class="bi bi-check2" viewBox="0 0 16 16">  <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" fill="#4bbf73"></path></svg>';
                setTimeout(() => {
                    this.innerHTML = '<svg width="15" height="15" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.625 3.125C6.34886 3.125 6.125 3.34886 6.125 3.625V4.875H13.375C14.3415 4.875 15.125 5.6585 15.125 6.625V13.875H16.375C16.6511 13.875 16.875 13.6511 16.875 13.375V3.625C16.875 3.34886 16.6511 3.125 16.375 3.125H6.625ZM15.125 15.125H16.375C17.3415 15.125 18.125 14.3415 18.125 13.375V3.625C18.125 2.6585 17.3415 1.875 16.375 1.875H6.625C5.6585 1.875 4.875 2.6585 4.875 3.625V4.875H3.625C2.6585 4.875 1.875 5.6585 1.875 6.625V16.375C1.875 17.3415 2.6585 18.125 3.625 18.125H13.375C14.3415 18.125 15.125 17.3415 15.125 16.375V15.125ZM13.875 6.625C13.875 6.34886 13.6511 6.125 13.375 6.125H3.625C3.34886 6.125 3.125 6.34886 3.125 6.625V16.375C3.125 16.6511 3.34886 16.875 3.625 16.875H13.375C13.6511 16.875 13.875 16.6511 13.875 16.375V6.625Z" fill="rgba(51, 102, 255, 1)"></path></svg>';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy transfer content:', err);
            });
        });
    }
});
