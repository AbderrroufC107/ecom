		</div>

	</div>

	<script src="js/jquery-2.2.4.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script src="js/jquery.dataTables.min.js"></script>
	<script src="js/dataTables.bootstrap.min.js"></script>
	<script>
	if (window.jQuery && window.jQuery.fn) {
		var dummyApi = {
			search: function() { return this; },
			draw: function() { return this; },
			columns: function() { return this; },
			adjust: function() { return this; },
			on: function() { return this; },
			row: function() { return this; },
			rows: function() { return this; },
			data: function() { return this; },
			column: function() { return this; },
			settings: function() { return [{}]; }
		};
		var dummyFn = function() { return dummyApi; };
		dummyFn.isDataTable = function() { return true; };
		dummyFn.fnIsDataTable = function() { return true; };
		window.jQuery.fn.DataTable = dummyFn;
		window.jQuery.fn.dataTable = dummyFn;
	}
	</script>
	<script src="js/select2.full.min.js" defer></script>
	<script src="js/moment.min.js" defer></script>
	<script src="js/bootstrap-datepicker.js" defer></script>
	<script src="js/icheck.min.js" defer></script>
	<script src="js/jquery.slimscroll.min.js" defer></script>
	<script src="js/app.min.js" defer></script>
	<script src="js/on-off-switch.js" defer></script>
	<script src="js/on-off-switch-onload.js" defer></script>
	<!-- spa-navigation.js disabled: conflicts with React shell causing double-render/flickering -->

	<script>
		$(document).ready(function() {
		$(".top-cat").on('change',function(){
			var id=$(this).val();
			var dataString = 'id='+ id;
			$.ajax
			({
				type: "POST",
				url: "get-mid-category.php",
				data: dataString,
				cache: false,
				success: function(html)
				{
					$(".mid-cat").html(html);
				}
			});			
		});
		$(".mid-cat").on('change',function(){
			var id=$(this).val();
			var dataString = 'id='+ id;
			$.ajax
			({
				type: "POST",
				url: "get-end-category.php",
				data: dataString,
				cache: false,
				success: function(html)
				{
					$(".end-cat").html(html);
				}
			});			
		});
	</script>

	<script>
	  $(function () {

	    //Initialize Select2 Elements
	    $(".select2").select2();

	    //Date picker
	    $('#datepicker').datepicker({
	      autoclose: true,
	      format: 'dd-mm-yyyy',
	      todayBtn: 'linked',
	    });

	    $('#datepicker1').datepicker({
	      autoclose: true,
	      format: 'dd-mm-yyyy',
	      todayBtn: 'linked',
	    });

	    //iCheck for checkbox and radio inputs
	    $('input[type="checkbox"].minimal, input[type="radio"].minimal').iCheck({
	      checkboxClass: 'icheckbox_minimal-blue',
	      radioClass: 'iradio_minimal-blue'
	    });
	    //Red color scheme for iCheck
	    $('input[type="checkbox"].minimal-red, input[type="radio"].minimal-red').iCheck({
	      checkboxClass: 'icheckbox_minimal-red',
	      radioClass: 'iradio_minimal-red'
	    });
	    //Flat red color scheme for iCheck
	    $('input[type="checkbox"].flat-red, input[type="radio"].flat-red').iCheck({
	      checkboxClass: 'icheckbox_flat-green',
	      radioClass: 'iradio_flat-green'
	    });



	    $("#example1").DataTable();
	    $('#example2').DataTable({
	      "paging": true,
	      "lengthChange": false,
	      "searching": false,
	      "ordering": true,
	      "info": true,
	      "autoWidth": false
	    });

	    $('#confirm-delete').on('show.bs.modal', function(e) {
	      $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
	    });
		
		$('#confirm-approve').on('show.bs.modal', function(e) {
	      $(this).find('.btn-ok').attr('href', $(e.relatedTarget).data('href'));
	    });
 
	  });

		function confirmDelete()
	    {
	        return confirm("Are you sure want to delete this data?");
	    }
	    function confirmActive()
	    {
	        return confirm("Are you sure want to Active?");
	    }
	    function confirmInactive()
	    {
	        return confirm("Are you sure want to Inactive?");
	    }

	</script>

	<script type="text/javascript">
		function showDiv(elem){
			if(elem.value == 0) {
		      	document.getElementById('photo_div').style.display = "none";
		      	document.getElementById('icon_div').style.display = "none";
		   	}
		   	if(elem.value == 1) {
		      	document.getElementById('photo_div').style.display = "block";
		      	document.getElementById('photo_div_existing').style.display = "block";
		      	document.getElementById('icon_div').style.display = "none";
		   	}
		   	if(elem.value == 2) {
		      	document.getElementById('photo_div').style.display = "none";
		      	document.getElementById('photo_div_existing').style.display = "none";
		      	document.getElementById('icon_div').style.display = "block";
    }
    }
    <?php 
    if (!function_exists('profiler_output')) {
        require_once __DIR__ . '/inc/profiler_output.php';
    }
    profiler_output(); 
    ?>
		function showContentInputArea(elem){
		   if(elem.value == 'Full Width Page Layout') {
		      	document.getElementById('showPageContent').style.display = "block";
		   } else {
		   		document.getElementById('showPageContent').style.display = "none";
		   }
		}
	</script>

	<script type="text/javascript">

        $(document).ready(function () {

            $("#btnAddNew").click(function () {

		        var rowNumber = $("#ProductTable tbody tr").length;

		        var trNew = "";              

		        var addLink = "<div class=\"upload-btn" + rowNumber + "\"><input type=\"file\" name=\"photo[]\"  style=\"margin-bottom:5px;\"></div>";
		           
		        var deleteRow = "<a href=\"javascript:void()\" class=\"Delete btn btn-danger btn-xs\">X</a>";

		        trNew = trNew + "<tr> ";

		        trNew += "<td>" + addLink + "</td>";
		        trNew += "<td style=\"width:28px;\">" + deleteRow + "</td>";

		        trNew = trNew + " </tr>";

		        $("#ProductTable tbody").append(trNew);

		    });

		    $('#ProductTable').delegate('a.Delete', 'click', function () {
		        $(this).parent().parent().fadeOut('slow').remove();
		        return false;
		    });

        });



        var items = [];
        for( i=1; i<=24; i++ ) {
        	items[i] = document.getElementById("tabField"+i);
        }

        if (items[1]) {
			items[1].style.display = 'block';
			items[2].style.display = 'block';
			items[3].style.display = 'block';
			items[4].style.display = 'none';

			items[5].style.display = 'block';
			items[6].style.display = 'block';
			items[7].style.display = 'block';
			items[8].style.display = 'none';

			items[9].style.display = 'block';
			items[10].style.display = 'block';
			items[11].style.display = 'block';
			items[12].style.display = 'none';

			items[13].style.display = 'block';
			items[14].style.display = 'block';
			items[15].style.display = 'block';
			items[16].style.display = 'none';

			items[17].style.display = 'block';
			items[18].style.display = 'block';
			items[19].style.display = 'block';
			items[20].style.display = 'none';

			items[21].style.display = 'block';
			items[22].style.display = 'block';
			items[23].style.display = 'block';
			items[24].style.display = 'none';
        }

		function funcTab1(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[1].style.display = 'block';
		       	items[2].style.display = 'block';
		       	items[3].style.display = 'block';
		       	items[4].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[1].style.display = 'none';
		       	items[2].style.display = 'none';
		       	items[3].style.display = 'none';
		       	items[4].style.display = 'block';
			}
		};

		function funcTab2(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[5].style.display = 'block';
		       	items[6].style.display = 'block';
		       	items[7].style.display = 'block';
		       	items[8].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[5].style.display = 'none';
		       	items[6].style.display = 'none';
		       	items[7].style.display = 'none';
		       	items[8].style.display = 'block';
			}
		};

		function funcTab3(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[9].style.display = 'block';
		       	items[10].style.display = 'block';
		       	items[11].style.display = 'block';
		       	items[12].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[9].style.display = 'none';
		       	items[10].style.display = 'none';
		       	items[11].style.display = 'none';
		       	items[12].style.display = 'block';
			}
		};

		function funcTab4(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[13].style.display = 'block';
		       	items[14].style.display = 'block';
		       	items[15].style.display = 'block';
		       	items[16].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[13].style.display = 'none';
		       	items[14].style.display = 'none';
		       	items[15].style.display = 'none';
		       	items[16].style.display = 'block';
			}
		};

		function funcTab5(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[17].style.display = 'block';
		       	items[18].style.display = 'block';
		       	items[19].style.display = 'block';
		       	items[20].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[17].style.display = 'none';
		       	items[18].style.display = 'none';
		       	items[19].style.display = 'none';
		       	items[20].style.display = 'block';
			}
		};

		function funcTab6(elem) {
			var txt = elem.value;
			if(txt == 'Image Advertisement') {
				items[21].style.display = 'block';
		       	items[22].style.display = 'block';
		       	items[23].style.display = 'block';
		       	items[24].style.display = 'none';
			} 
			if(txt == 'Adsense Code') {
				items[21].style.display = 'none';
		       	items[22].style.display = 'none';
		       	items[23].style.display = 'none';
		       	items[24].style.display = 'block';
			}
		};



        
    </script>

<?php Profiler::checkpoint('full_page_render'); ?>
<?php if (!empty($admin_auto_refresh['enabled'])): ?>
<style>
		.admin-live-refresh-toast {
			position: fixed;
			left: 20px;
			bottom: 20px;
			z-index: 9999;
			display: none;
			max-width: 340px;
			padding: 10px 14px;
			border-radius: 10px;
			background: rgba(28, 36, 52, 0.94);
			color: #fff;
			font-size: 13px;
			line-height: 1.5;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.22);
		}
		.admin-live-refresh-toast.is-visible {
			display: block;
		}
	</style>
	<script>
		(function(config, $) {
			if (!config || !config.enabled || typeof $ === 'undefined') {
				return;
			}

			var currentVersion = String(config.version || '');
			var pollInterval = parseInt(config.interval_ms || 20000, 10);
			var pollInFlight = false;
			var pendingReload = false;
			var hasDirtyForm = false;
			var toast = null;

			if (pollInterval < 10000) {
				pollInterval = 10000;
			}

			function ensureToast() {
				if (toast) {
					return toast;
				}

				toast = document.createElement('div');
				toast.className = 'admin-live-refresh-toast';
				toast.id = 'admin-live-refresh-toast';
				document.body.appendChild(toast);
				return toast;
			}

			function showPendingToast() {
				var node = ensureToast();
				node.textContent = config.pending_text || 'يوجد تحديث جديد وسيتم تطبيقه تلقائياً';
				node.className = 'admin-live-refresh-toast is-visible';
			}

			function hidePendingToast() {
				if (!toast) {
					return;
				}

				toast.className = 'admin-live-refresh-toast';
			}

			function canReloadNow() {
				if (document.hidden) {
					return false;
				}

				if (hasDirtyForm) {
					return false;
				}

				if ($('.modal.in, .modal.show').length > 0) {
					return false;
				}

				return true;
			}

			function reloadIfSafe() {
				if (!pendingReload || !canReloadNow()) {
					return;
				}

				hidePendingToast();
				showToast('يوجد تحديث جديد<br><button onclick="window.location.reload()" style="margin-top:6px;padding:4px 12px;border-radius:4px;border:none;background:#fff;color:#3b82f6;cursor:pointer;font-weight:700;">تحديث الصفحة</button>', 'info', 15000);
			}

			function markDirty() {
				hasDirtyForm = true;
			}

			function clearDirty() {
				hasDirtyForm = false;
				reloadIfSafe();
			}

			function requestVersion() {
				if (pollInFlight) {
					return;
				}

				pollInFlight = true;

				$.ajax({
					url: config.endpoint || 'live-update-status.php',
					type: 'GET',
					dataType: 'json',
					cache: false,
					data: config.params || {}
				}).done(function(response) {
					if (!response || !response.success) {
						return;
					}

					var nextVersion = String(response.version || '');
					if (currentVersion === '') {
						currentVersion = nextVersion;
						return;
					}

					if (nextVersion !== '' && nextVersion !== currentVersion) {
						pendingReload = true;
						showPendingToast();
						reloadIfSafe();
					}
				}).always(function() {
					pollInFlight = false;
				});
			}

			$(document).on('input change', 'form input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), form textarea, form select', markDirty);
			$(document).on('submit', 'form', clearDirty);
			$(document).on('click', '.modal .btn, [data-dismiss="modal"]', function() {
				window.setTimeout(reloadIfSafe, 300);
			});

			document.addEventListener('visibilitychange', function() {
				if (!document.hidden) {
					if (pendingReload) {
						reloadIfSafe();
					} else {
						requestVersion();
					}
				}
			});

			window.setInterval(function() {
				if (pendingReload) {
					reloadIfSafe();
					return;
				}

				requestVersion();
			}, pollInterval);
		})(<?php echo json_encode($admin_auto_refresh, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, window.jQuery);
	</script>
	<?php endif; ?>

	<script>
	// ECOTRACK Auto-Sync Engine
	(function($) {
		if (typeof $ === 'undefined') return;
		function runEcotrackAutoSync() {
			$.ajax({
				url: 'ajax-ecotrack-autosync.php',
				method: 'GET',
				dataType: 'json',
				success: function(response) {
					if (response && response.synced > 0) {
						console.log('Ecotrack Auto-Sync:', response.message);
						// Optionally, trigger a live-refresh if configured
						if (window.adminAutoRefreshTrigger) window.adminAutoRefreshTrigger();
					}
				}
			});
		}
		// Run after 5 seconds, then every 3 minutes
		setTimeout(runEcotrackAutoSync, 5000);
		setInterval(runEcotrackAutoSync, 180000);
	})(window.jQuery);
	</script>
	<?php if(!isset($_GET['iframe'])): ?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			window.setTimeout(function() {
				if (!document.body.classList.contains('admin-react-ready')) {
					document.body.classList.remove('admin-react-pending');
				}
			}, 3500);
		});
	</script>
	<?php $admin_react_js_version = file_exists(__DIR__ . '/dist/admin-react.js') ? filemtime(__DIR__ . '/dist/admin-react.js') : time(); ?>
	<script type="module" src="dist/admin-react.js?v=<?php echo $admin_react_js_version; ?>" onerror="try{fetch('log_error.php?err='+encodeURIComponent('[LoadError] admin-react.js failed to load: '+this.src))}catch(e){}document.body.classList.remove('admin-react-pending');"></script>
	<script nomodule>
		try{fetch('log_error.php?err='+encodeURIComponent('[LoadError] Module scripts not supported, falling back'))}catch(e){}
		document.body.classList.remove('admin-react-pending');
	</script>
	<?php else: ?>
	<script>
		document.body.classList.remove('admin-react-pending');
		document.body.style.background = 'transparent';
	</script>
	<?php endif; ?>

	<!-- Sidebar Chevron Init (CSS grid controlled by React state) -->
	<script>
	(function() {
		function init() {
			document.querySelectorAll('.saas-nav-item').forEach(function(item) {
				if (item._subnavInit) return;
				item._subnavInit = true;
				var btn = item.querySelector('.saas-nav-link[role="button"]');
				if (!btn) return;
				var chevron = btn.querySelector('svg:last-child');
				var hasActive = item.querySelector('.is-active');
				if (chevron) chevron.classList.toggle('is-open', !!hasActive);
			});
		}
		var observer = new MutationObserver(function() { init(); });
		observer.observe(document.body, { childList: true, subtree: true });
		init();
	})();
	</script>

	<!-- WebSocket Real-time Updates -->
	<script>
	(function() {
		var WS_HOST = '127.0.0.1';
		var WS_PORT = 9001;
		var WS_URL = 'ws://' + WS_HOST + ':' + WS_PORT;
		var ws = null;
		var reconnectDelay = 2000;
		var maxReconnectDelay = 30000;

		function connect() {
			try {
				ws = new WebSocket(WS_URL);

				ws.onopen = function() {
					console.log('[WS] Connected to WebSocket server');
					reconnectDelay = 2000;
					showToast('تم الاتصال بالخادم', 'success', 2000);
				};

				ws.onmessage = function(event) {
					try {
						var msg = JSON.parse(event.data);
						handleMessage(msg);
					} catch(e) {
						console.log('[WS] Parse error:', e);
					}
				};

				ws.onclose = function() {
					console.log('[WS] Disconnected, reconnecting in ' + (reconnectDelay/1000) + 's...');
					setTimeout(connect, reconnectDelay);
					reconnectDelay = Math.min(reconnectDelay * 1.5, maxReconnectDelay);
				};

				ws.onerror = function(err) {
					console.log('[WS] Error:', err);
					ws.close();
				};
			} catch(e) {
				console.log('[WS] Connection failed:', e);
				setTimeout(connect, reconnectDelay);
			}
		}

		function handleMessage(msg) {
			var type = msg.type || '';
			var payload = msg.payload || msg;

			switch(type) {
				case 'order.new':
					handleNewOrder(payload);
					break;
				case 'order.status':
					handleOrderStatus(payload);
					break;
				case 'stock.update':
					handleStockUpdate(payload);
					break;
				case 'connected':
					console.log('[WS] Server:', payload.message);
					break;
				case 'pong':
					break;
			}
		}

		function handleNewOrder(order) {
			var name = order.customer_name || 'عميل';
			var total = parseInt(order.total_price || 0);
			var phone = order.customer_phone || '';
			var wilaya = order.wilaya || '';
			var product = order.product_name || '';
			var orderId = order.id || '';

			var body = '<strong>عميل:</strong> ' + name + '<br>' +
			           '<strong>الهاتف:</strong> ' + phone + '<br>' +
			           '<strong>المنتج:</strong> ' + product + '<br>' +
			           '<strong>المبلغ:</strong> ' + total.toLocaleString('ar-DZ') + ' د.ج<br>' +
			           '<strong>الولاية:</strong> ' + wilaya;

			showNotification('طلب جديد #' + orderId, body, 'order');

			// Play notification sound
			playNotificationSound();

			// Request browser notification
			if ('Notification' in window && Notification.permission === 'granted') {
				new Notification('طلب جديد #' + orderId, {
					body: name + ' - ' + total.toLocaleString('ar-DZ') + ' د.ج',
				});
			}

			// Show notification on orders page (no auto-reload)
			if (window.location.pathname.indexOf('order.php') !== -1) {
				showToast('<strong>طلب جديد #' + orderId + '</strong><br><button onclick="window.location.reload()" style="margin-top:6px;padding:4px 12px;border-radius:4px;border:none;background:#00a65a;color:#fff;cursor:pointer;font-weight:700;">تحديث الصفحة</button>', 'info', 8000);
			}
		}

		function handleOrderStatus(order) {
			var name = order.customer_name || 'عميل';
			var newStatus = order.new_status || '';
			var prevStatus = order.previous_status || '';
			var orderId = order.id || '';

			showToast('تم تغيير حالة الطلب #' + orderId + ' من "' + prevStatus + '" إلى "' + newStatus + '"', 'info', 4000);

			// Show notification on orders page (no auto-reload)
			if (window.location.pathname.indexOf('order.php') !== -1 ||
			    window.location.pathname.indexOf('order-details.php') !== -1) {
				showToast('تم تغيير حالة الطلب #' + orderId + ' من "' + prevStatus + '" إلى "' + newStatus + '"<br><button onclick="window.location.reload()" style="margin-top:6px;padding:4px 12px;border-radius:4px;border:none;background:#00a65a;color:#fff;cursor:pointer;font-weight:700;">تحديث</button>', 'info', 8000);
			}
		}

		function handleStockUpdate(product) {
			showToast('تم تحديث مخزون: ' + (product.name || 'منتج') + ' - الكمية: ' + (product.qty || 0), 'info', 3000);
		}

		function showNotification(title, body, type) {
			var container = getNotificationContainer();
			var el = document.createElement('div');
			el.className = 'ws-notification ws-notification-' + (type || 'info');
			el.innerHTML = '<div class="ws-notification-header"><span class="ws-notification-title">' + title + '</span><button class="ws-notification-close" onclick="this.parentElement.parentElement.remove()">×</button></div><div class="ws-notification-body">' + body + '</div>';
			container.appendChild(el);

			// Auto remove after 10 seconds
			setTimeout(function() {
				if (el.parentElement) {
					el.style.opacity = '0';
					el.style.transform = 'translateX(-100%)';
					setTimeout(function() { el.remove(); }, 300);
				}
			}, 10000);
		}

		function showToast(message, type, duration) {
			type = type || 'info';
			duration = duration || 3000;

			var existing = document.getElementById('ws-toast');
			if (existing) existing.remove();

			var toast = document.createElement('div');
			toast.id = 'ws-toast';
			toast.className = 'ws-toast ws-toast-' + type;
			toast.innerHTML = message;
			toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;padding:12px 24px;border-radius:8px;color:#fff;font-size:14px;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,0.3);transition:all 0.3s;opacity:1;';

			var colors = { success: '#10b981', info: '#3b82f6', warning: '#f59e0b', danger: '#ef4444' };
			toast.style.background = colors[type] || colors.info;

			document.body.appendChild(toast);
			setTimeout(function() {
				toast.style.opacity = '0';
				toast.style.top = '0';
				setTimeout(function() { toast.remove(); }, 300);
			}, duration);
		}

		function getNotificationContainer() {
			var c = document.getElementById('ws-notifications');
			if (!c) {
				c = document.createElement('div');
				c.id = 'ws-notifications';
				c.style.cssText = 'position:fixed;top:80px;left:20px;z-index:99998;width:360px;max-height:calc(100vh - 100px);overflow-y:auto;display:flex;flex-direction:column;gap:10px;';
				document.body.appendChild(c);
			}
			return c;
		}

		function playNotificationSound() {
			try {
				var ctx = new (window.AudioContext || window.webkitAudioContext)();
				var osc = ctx.createOscillator();
				var gain = ctx.createGain();
				osc.connect(gain);
				gain.connect(ctx.destination);
				osc.frequency.value = 800;
				osc.type = 'sine';
				gain.gain.value = 0.3;
				osc.start();
				osc.stop(ctx.currentTime + 0.2);
			} catch(e) {}
		}

		// Request notification permission
		if ('Notification' in window && Notification.permission === 'default') {
			Notification.requestPermission();
		}

		// Heartbeat
		setInterval(function() {
			if (ws && ws.readyState === WebSocket.OPEN) {
				ws.send(JSON.stringify({type: 'ping'}));
			}
		}, 30000);

		// Connect
		connect();
	})();
	</script>
	<style>
		.ws-notification {
			background: #1e293b;
			border-radius: 10px;
			padding: 14px;
			color: #fff;
			box-shadow: 0 4px 20px rgba(0,0,0,0.3);
			transition: all 0.3s;
			border-right: 4px solid #3b82f6;
		}
		.ws-notification-order { border-right-color: #10b981; }
		.ws-notification-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 8px;
		}
		.ws-notification-title {
			font-weight: 700;
			font-size: 14px;
		}
		.ws-notification-close {
			background: none;
			border: none;
			color: #94a3b8;
			font-size: 18px;
			cursor: pointer;
			padding: 0 4px;
		}
		.ws-notification-close:hover { color: #fff; }
		.ws-notification-body {
			font-size: 13px;
			line-height: 1.6;
			color: #cbd5e1;
		}
	</style>
</body>
</html>
