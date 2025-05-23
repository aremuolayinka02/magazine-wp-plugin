<?php
if (!defined('ABSPATH')) {
    exit;
}

function rsa_magazine_viewer_shortcode_function() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        $redirect_url = get_option('rsa_magazines_login_redirect', wp_login_url());
        wp_redirect($redirect_url);
        exit;
    }

    // Get magazine ID from URL
    $magazine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (empty($magazine_id)) {
        return '<div class="magazine-error">Invalid magazine parameters.</div>';
    }

    // Get magazine details
    global $wpdb;
    $table_name = $wpdb->prefix . 'rsa_digital_magazines';
    $magazine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $magazine_id));

    if (!$magazine) {
        return '<div class="magazine-error">Magazine not found.</div>';
    }

    // Enqueue necessary scripts
    wp_enqueue_script('pdf-js', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js', array(), '3.4.120');
    wp_enqueue_script('three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', array(), 'r128');
    
    // Add nonce for security
    $nonce = wp_create_nonce('rsa_magazine_view_pdf');

    ob_start();
    ?>
    <div class="rsa-pdf-viewer-container">
        <div class="pdf-viewer-header">
            <h1><?php echo esc_html($magazine->title); ?></h1>
            <div class="pdf-controls">
                <button id="prev-page">Previous</button>
                <span>Page: <span id="page-num"></span> / <span id="page-count"></span></span>
                <button id="next-page">Next</button>
                <button id="toggle-3d">Toggle 3D View</button>
            </div>
        </div>
        
        <div id="pdf-viewer-canvas-container">
            <canvas id="pdf-viewer-canvas"></canvas>
        </div>

        <div id="3d-viewer-container" style="display: none;">
            <canvas id="3d-viewer-canvas"></canvas>
        </div>
    </div>

    <style>
        .rsa-pdf-viewer-container {
            max-width: 100%;
            margin: 20px auto;
            padding: 20px;
        }
        .pdf-viewer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .pdf-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        #pdf-viewer-canvas-container {
            width: 100%;
            overflow: auto;
            background: #f5f5f5;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        #pdf-viewer-canvas {
            display: block;
            margin: 0 auto;
        }
        #3d-viewer-container {
            width: 100%;
            height: 600px;
            background: #000;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';

            // Use AJAX to get the PDF content securely
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'rsa_get_magazine_pdf',
                    magazine_id: <?php echo $magazine_id; ?>,
                    nonce: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success && response.data.pdf_url) {
                        initPdfViewer(response.data.pdf_url);
                        
                        // Clean URL if PDF parameter was passed
                        if (window.location.href.indexOf('pdf=') > -1) {
                            history.replaceState({}, document.title, window.location.pathname + '?id=<?php echo $magazine_id; ?>');
                        }
                    } else {
                        alert('Error loading PDF: ' + (response.data.message || 'Unknown error'));
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    }
                },
                error: function() {
                    alert('Error loading PDF. Please try again later.');
                }
            });

            function initPdfViewer(pdfUrl) {
                let pdfDoc = null;
                let pageNum = 1;
                let pageRendering = false;
                let pageNumPending = null;
                const canvas = document.getElementById('pdf-viewer-canvas');
                const ctx = canvas.getContext('2d');

                // Load the PDF
                pdfjsLib.getDocument({
                    url: pdfUrl,
                    withCredentials: true // For secure PDFs
                }).promise.then(function(pdfDoc_) {
                    pdfDoc = pdfDoc_;
                    document.getElementById('page-count').textContent = pdfDoc.numPages;
                    renderPage(pageNum);
                }).catch(function(error) {
                    console.error('Error loading PDF:', error);
                    alert('Error loading PDF. Please try again later.');
                });

                function renderPage(num) {
                    pageRendering = true;
                    pdfDoc.getPage(num).then(function(page) {
                        const viewport = page.getViewport({scale: 1.5});
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const renderContext = {
                            canvasContext: ctx,
                            viewport: viewport
                        };
                        const renderTask = page.render(renderContext);

                        renderTask.promise.then(function() {
                            pageRendering = false;
                            if (pageNumPending !== null) {
                                renderPage(pageNumPending);
                                pageNumPending = null;
                            }
                        });
                    });

                    document.getElementById('page-num').textContent = num;
                }

                document.getElementById('prev-page').addEventListener('click', function() {
                    if (pageNum <= 1) return;
                    pageNum--;
                    renderPage(pageNum);
                });

                document.getElementById('next-page').addEventListener('click', function() {
                    if (pageNum >= pdfDoc.numPages) return;
                    pageNum++;
                    renderPage(pageNum);
                });

                // 3D viewer setup
                let scene, camera, renderer;
                const init3DViewer = () => {
                    scene = new THREE.Scene();
                    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
                    renderer = new THREE.WebGLRenderer({
                        canvas: document.getElementById('3d-viewer-canvas'),
                        antialias: true
                    });
                    
                    renderer.setSize(window.innerWidth, 600);
                    camera.position.z = 5;

                    // Add some 3D elements to represent pages
                    const geometry = new THREE.BoxGeometry(1, 1.4, 0.1);
                    const material = new THREE.MeshBasicMaterial({color: 0xffffff});
                    const pages = new THREE.Group();

                    for(let i = 0; i < 5; i++) {
                        const page = new THREE.Mesh(geometry, material);
                        page.position.z = i * 0.1;
                        pages.add(page);
                    }

                    scene.add(pages);

                    function animate() {
                        requestAnimationFrame(animate);
                        pages.rotation.y += 0.01;
                        renderer.render(scene, camera);
                    }
                    animate();
                };

                document.getElementById('toggle-3d').addEventListener('click', function() {
                    const pdfContainer = document.getElementById('pdf-viewer-canvas-container');
                    const viewer3D = document.getElementById('3d-viewer-container');
                    
                    if(viewer3D.style.display === 'none') {
                        pdfContainer.style.display = 'none';
                        viewer3D.style.display = 'block';
                        if(!scene) init3DViewer();
                    } else {
                        pdfContainer.style.display = 'block';
                        viewer3D.style.display = 'none';
                    }
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
}