# Trakli Cloud Plugin

This plugin controls whether the Trakli webservice operates in free or paid mode. It manages the subscription functionality, pricing plans, and feature access based on the current mode.

## Features

- Toggle between free and paid service modes
- Configurable pricing plans when in paid mode
- Simple API endpoints for checking service status and plans
- Environment-based configuration

## Installation

1. Clone or download this plugin into your `plugins/cloud` directory.

2. Publish the configuration file:
   ```bash
   php artisan vendor:publish --tag=cloud-config
   ```
   
   Or manually copy the config file:
   ```bash
   cp plugins/cloud/config/cloudplans.php config/cloudplans.php
   ```

3. The configuration will be available at `config/cloudplans.php` where you can customize the settings.

4. If you modify the configuration, clear the config cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Configuration

### Service Modes

1. **Free Mode** (default):
   - All features are available without payment
   - No subscription required
   - Set `CLOUD_FREE_PLAN_ENABLED=true`

2. **Paid Mode**:
   - Requires subscription after trial period
   - Configure pricing and plans
   - Set `CLOUD_FREE_PLAN_ENABLED=false`

### Environment Variables

```env
# Enable/disable free mode (when true, all features are free)
CLOUD_FREE_PLAN_ENABLED=true

# Pricing (in cents, only used when FREE_PLAN_ENABLED=false)
CLOUD_PLAN_MONTHLY_PRICE=500    # $5.00
CLOUD_PLAN_YEARLY_PRICE=5000    # $50.00 (about 17% off monthly)
```

### Regions

Currently supported regions:
- US (United States)
- EU (Europe)
- UK (United Kingdom)

All regions use USD as the currency.

## API Endpoints

### Get Plans

The `GET /api/cloud/plans` endpoint retrieves available subscription plans. The response structure changes based on whether the optional `region` query parameter is provided.

#### Get Plans for a Specific Region

When the `region` parameter is included (e.g., `?region=us`), the API returns a detailed response for that single region.

**Example Request:**
```http
GET /api/cloud/plans?region=us
```

**Example Response (`region=us`):**
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        "overview": { /* ... */ },
        "region": "United States",
        "currency": "USD",
        "trial_days": 3,
        "free_plan_enabled": false,
        "plans": [
            {
                "id": "monthly",
                "name": "Monthly",
                "interval": "month",
                "features": [/* ... */],
                "cta": { /* ... */ },
                "price": 5.00,
                "price_formatted": "$5.00"
            },
            {
                "id": "yearly",
                "name": "Yearly",
                "interval": "year",
                "features": [/* ... */],
                "cta": { /* ... */ },
                "price": 50.00,
                "price_formatted": "$50.00"
            }
        ]
    }
}
```

#### Get All Plans and Regional Pricing

When the `region` parameter is **omitted**, the API returns a consolidated response containing base plan information and a breakdown of pricing for all available regions. This avoids data duplication.

**Example Request:**
```http
GET /api/cloud/plans
```

**Example Response (All Regions):**
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        "overview": { /* ... */ },
        "trial_days": 3,
        "free_plan_enabled": false,
        "plans": [
            {
                "id": "monthly",
                "name": "Monthly",
                "interval": "month",
                "features": [/* ... */],
                "cta": { /* ... */ }
            },
            {
                "id": "yearly",
                "name": "Yearly",
                "interval": "year",
                "features": [/* ... */],
                "cta": { /* ... */ }
            }
        ],
        "regions": {
            "us": {
                "name": "United States",
                "currency": "USD",
                "prices": {
                    "monthly": {
                        "price": 5.00,
                        "price_formatted": "$5.00"
                    },
                    "yearly": {
                        "price": 50.00,
                        "price_formatted": "$50.00"
                    }
                }
            },
            "eu": {
                "name": "Europe",
                "currency": "EUR",
                "prices": {
                    "monthly": {
                        "price": 5.00,
                        "price_formatted": "€5.00"
                    },
                    "yearly": {
                        "price": 50.00,
                        "price_formatted": "€50.00"
                    }
                }
            }
        }
    }
}
```

### Get Benefits

```http
GET /api/cloud/benefits
```

**Example Response:**
```json
{
    "overview": {
        "title": "Why Create a Trakli Cloud Account?",
        "description": "..."
    },
    "benefits": [
        {
            "title": "Access Anywhere",
            "description": "..."
        }
    ],
    "trial_days": 3
}
```

## Development

### Configuration

Edit `config/cloudplans.php` to modify:
- Plan features
- Benefits
- Trial period
- Region settings

### Adding New Regions

1. Add a new entry to the `regions` array in `config/cloudplans.php`
2. The region key should be a 2-3 letter code (e.g., 'ca' for Canada)
3. Set the name and currency for the region

### Testing

Run the test suite:

```bash
php artisan test
```

## License

This plugin is open-source software licensed under the [MIT License](https://opensource.org/licenses/MIT).
