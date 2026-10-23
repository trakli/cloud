# Trakli billing UI layer

Include this directory in the host web UI's `TRAKLI_UI_LAYERS` setting and enable the matching backend plugin. The layer supplies the Plan and billing tab in Settings, English messages and billing flow tests. `/billing` redirects to that tab and keeps its query, so checkout and portal returns still arrive.

Yearly billing is selected by default. Checkout and portal returns retain the existing billing address. Bank management is shown only when a bank UI layer is installed.

Run the host web UI's lint, format, tests and build with this layer enabled. The host provides authentication, API access, components and layout.
