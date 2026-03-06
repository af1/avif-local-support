# AVIF Local Support Extended

A practical fork of AVIF Local Support focused on server-safe conversion controls, broader AVIF coverage (including lightboxes and non-library JPEGs), clearer progress/logging, and production-ready defaults.

## Description

**AVIF Local Support Extended** is a fork of the original **AVIF Local Support** plugin by **David Degner (ddegner)**.

Original project: https://github.com/ddegner/avif-local-support

Fork project (you are here): https://github.com/af1/avif-local-support

## Why This Fork Exists

This fork exists to make AVIF conversion easier to run on real sites.

- Adds CPU usage controls so conversion jobs do not peg the server at 100%.
- Serves AVIF files in more front-end contexts, including lightboxes.
- Converts JPEG files outside the Media Library (uploads folders and nested paths), not only attachment records.
- Adds non-blocking upload conversion by queuing work in the background.
- Improves conversion progress/status visibility so admins can see what is happening during long runs.
- Adds clearer logs and run diagnostics for troubleshooting.
- Improves handling of cases where AVIF output is larger than the source JPEG (retry controls and size policy options).
- Adds more practical defaults for production use (safer thread defaults and upload conversion off by default).


## License

This fork remains licensed under the **GNU General Public License v2 or later (GPLv2+)**, same as the original project.

- https://www.gnu.org/licenses/gpl-2.0.html

## Installation

1. Upload this plugin to `/wp-content/plugins/avif-local-support`.
2. Activate it from WordPress admin.
3. Open plugin settings/tools to configure conversion behavior.
