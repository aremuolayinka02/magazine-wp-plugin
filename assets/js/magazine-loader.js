(function ($) {
  window.initMagazineContainer = function (container) {
    var $container = $(container);
    var data = $container.data();
    var timestamp = new Date().getTime();

    console.log("Initializing magazine container:", data);

    $.ajax({
      url: data.restUrl + "shortcode/" + data.shortcode + "?_=" + timestamp,
      method: "GET",
      cache: false,
      headers: {
        "X-WP-Nonce": data.nonce,
        "Cache-Control": "no-cache",
        Pragma: "no-cache",
      },
    })
      .then(function (response) {
        console.log("Shortcode response:", response);
        console.log("Viewer Page ID from response:", response.viewerPageId);

        if (!response || !response.shortcode) {
          throw new Error("Invalid shortcode response");
        }

        return $.ajax({
          url:
            data.restUrl +
            "magazines/" +
            response.shortcode.list_type +
            "?_=" +
            timestamp,
          method: "GET",
          cache: false,
          headers: {
            "X-WP-Nonce": data.nonce,
            "Cache-Control": "no-cache",
            Pragma: "no-cache",
          },
        }).then(function (magazineResponse) {
          console.log("Magazine response:", magazineResponse);
          return {
            shortcode: response.shortcode,
            magazines: magazineResponse.magazines || [],
            viewerPageId: response.viewerPageId || "",
          };
        });
      })
      .then(function (data) {
        console.log("Final data for rendering:", data);
        console.log("Viewer Page ID for URL:", data.viewerPageId);

        if (!data.magazines || !data.magazines.length) {
          $container.html("<p>No magazines found</p>");
          return;
        }

        var html = "<style>";
        var containerClass = data.shortcode.container_class;

        // First add the default styles
        html += data.shortcode.css;

        // Then add container-specific styles with higher specificity
        if (containerClass) {
          var cssRules = data.shortcode.css.split("}");
          var containerStyles = cssRules
            .map(function (rule) {
              if (!rule.trim()) return "";

              var parts = rule.split("{");
              if (parts.length !== 2) return "";

              var selector = parts[0].trim();
              var styles = parts[1].trim();

              // Add container class to create more specific selectors
              if (selector.includes("&")) {
                // Handle parent reference
                return (
                  selector.replace("&", "." + containerClass) +
                  "{" +
                  styles +
                  "}"
                );
              } else {
                // Add container class as parent
                return (
                  "." + containerClass + " " + selector + "{" + styles + "} "
                );
              }
            })
            .join("\n");

          // Add container-specific styles after default styles
          html += "\n/* Container-specific styles */\n" + containerStyles;
        }

        html += "</style>";

        // Add container wrapper with proper classes
        var wrapperClasses = [];
        if (data.shortcode.list_type !== "featured") {
          wrapperClasses.push(
            data.shortcode.display_type === "scroll"
              ? "magazine-scroll"
              : "magazine-grid"
          );
        }
        if (containerClass) {
          wrapperClasses.unshift(containerClass);
        }
        if (wrapperClasses.length > 0) {
          html += '<div class="' + wrapperClasses.join(" ") + '">';
        }

        // Add magazine items
        data.magazines.forEach(function (magazine) {
          var template = data.shortcode.template;

          if (
            data.shortcode.list_type === "digital" ||
            data.shortcode.list_type === "featured"
          ) {
            if (!magazine.pdf_file) {
              template = '<div class="magazine-error">PDF file missing</div>';
            } else {
              // Get the viewer page ID from the response
              var viewerPageId = data.viewerPageId || "";
              console.log(
                "Using viewer page ID for magazine " + magazine.id + ":",
                viewerPageId
              );

              var magazineUrl = "";
              if ($container.data("loggedIn")) {
                if (viewerPageId) {
                  magazineUrl = "?page_id=" + viewerPageId;
                } else {
                  console.warn("No viewer page ID found! Check your settings.");
                  magazineUrl = "?";
                }
                magazineUrl +=
                  "&pdf=" +
                  encodeURIComponent(magazine.pdf_file) +
                  "&id=" +
                  magazine.id;
              } else {
                magazineUrl = $container.data("redirect");
              }

              console.log(
                "Generated URL for magazine " + magazine.id + ":",
                magazineUrl
              );

              template = template.replace(
                /href="{pdf_file}"/g,
                'href="' + magazineUrl + '"'
              );
            }
          } else if (
            data.shortcode.list_type === "hardcopy" &&
            magazine.payment_page_id
          ) {
            template = template.replace(
              /{payment_page}/g,
              "?page_id=" + magazine.payment_page_id
            );
          }

          html += template
            .replace(/{title}/g, magazine.title || "")
            .replace(/{featured_image}/g, magazine.featured_image || "")
            .replace(/{description}/g, magazine.description || "")
            .replace(/{issue_number}/g, magazine.issue_number || "")
            .replace(/{price}/g, magazine.price || "");
        });

        if (wrapperClasses.length > 0) {
          html += "</div>";
        }

        $container.html(html);
      })
      .fail(function (error) {
        console.error("Loading error:", error);
        console.log("Request details:", {
          shortcode: data.shortcode,
          restUrl: data.restUrl,
          nonce: data.nonce,
        });
        $container.html(
          "<p>Error loading content. Please check browser console for details.</p>"
        );
      });
  };

  // Initialize immediately when document is ready
  $(document).ready(function () {
    console.log("Looking for magazine containers...");
    var containers = $(".rsa-magazines-container");
    console.log("Found containers:", containers.length);

    containers.each(function () {
      initMagazineContainer(this);
    });
  });
})(jQuery);
