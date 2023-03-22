# Development environment

Clone this repository and build:

``` bash
cd /var/www/.../nextcloud/apps
git clone https://github.com/eneiluj/cospend-nc cospend
cd cospend
npm ci
npm run watch
```

Or if you want to use HMR (hot module replacement),
install the [Nextcloud HMR Enabler app](https://github.com/nextcloud/hmr_enabler)
and run this in cospend directory:
``` bash
npm run serve
```

# Public API

The root URL is `https://mynextcloud.org/index.php/apps/cospend`. Replace `mynextcloud.org` by your actual domain.

Plan was to make Cospend public API strictly identical to [IHateMoney API](https://ihatemoney.readthedocs.io/en/latest/api.html) but there is a restriction i couldn't bypass : the authentication system. IHateMoney uses the basic HTTP authentication, just like Nextcloud user authentication. So, to get a guest access to a Cospend project, this type of authentication was first rejected by Nextcloud user auth system and then accepted by Cospend with a huge latency.

So the most noticeable differences between IHateMoney API and Cospend API are :

* The password has to be included in the URL path, just after the project ID, like that : `https://mynextcloud.org/index.php/apps/cospend/api/myproject/projectPassword/bills`
* The parameter `payed_for` cannot be given multiple times like in IHateMoney. It has to be given once with coma separated values.


## How to connect
First and foremost, understand how to connect and get your first command working. There are two to three components you want to get:
* The project's ID `<project_id>` OR token `<project_token>`
* The project's password: `<project_password>`
* The account's credentials (if you're not using the share link method): `<username>`, `<token>`, `<password>`,... depending on the method used.

To correctly test your API for the first time, make sure you have one or two transactions entered through the web UI, otherwise you will get an empty response body despite the configuration being correct.

### First method: Share Link

This assumes you already have a project created, and want to use your API on specifically this project. This is basically a guest access to a project created beforehand. It doesn't depend on a specific account.

If you use this method, your API path will have the following shape: `<root_url>/api/projects/<project_token>/<project_password>/<command>`
#### Get the `<project_token>`



Go to the project settings in the UI, open the Sharing tab, and create a share link. Your link should look something like this:
`https://mynextcloud.org/apps/cospend/s/ba3355eaecc6254ad1755fa8e7cdf54a)`

Take note of the project's token (`ba3355eaecc6254ad1755fa8e7cdf54a`), that will act as your `<project_token>`.
#### Get the `<project_password>`
If you chose to set a password on your link, use it as your `<project_password>`.

**If you don't set a password on your share link, there will be a default password**: The default password is `no-pass`. It's not required when using the web view, but it is with the API, as explained earlier.
#### Trying
You can now try your first `<command>`: `/statistics`. If you've followed correctly, your request should look like this:

```bash
curl -s https://mynextcloud.org/index.php/apps/cospend/api/projects/ba3355eaecc6254ad1755fa8e7cdf54a/no-pass/statistics
```
and have a correct result:
<details>

```json
{
  "stats": [
    {
      "balance": -119,
      "filtered_balance": -119,
      "paid": 0,
      "spent": 119,
      "member": {
        "activated": true,
        "userid": "firstuserid",
        "name": "John Do",
        "id": 4,
        "weight": 1,
        "color": {
          "r": 110,
          "g": 166,
          "b": 143
        },
        "lastchanged": 1679234081
      }
    },
    {
      "balance": 119,
      "filtered_balance": 119,
      "paid": 520,
      "spent": 401,
      "member": {
        "activated": true,
        "userid": "seconduserid",
        "name": "Alice Foo",
        "id": 3,
        "weight": 1,
        "color": {
          "r": 0,
          "g": 130,
          "b": 201
        },
        "lastchanged": 1679234078
      }
    }
  ],
  "memberMonthlyPaidStats": {
    "2023-03": {
      "4": 0,
      "3": 520,
      "0": 520
    },
    "Average per month": {
      "4": 0,
      "3": 520,
      "0": 520
    }
  },
  "memberMonthlySpentStats": {
    "2023-03": {
      "4": 119,
      "3": 401,
      "0": 520
    },
    "Average per month": {
      "4": 119,
      "3": 401,
      "0": 520
    }
  },
  "categoryStats": [
    520
  ],
  "categoryMonthlyStats": [
    {
      "2023-03": 520,
      "Average per month": 520
    }
  ],
  "paymentModeStats": [
    520
  ],
  "paymentModeMonthlyStats": [
    {
      "2023-03": 520,
      "Average per month": 520
    }
  ],
  "categoryMemberStats": [
    {
      "4": 0,
      "3": 520
    }
  ],
  "memberIds": [
    4,
    3
  ],
  "allMemberIds": [
    4,
    3
  ],
  "membersPaidFor": {
    "4": {
      "4": 0,
      "3": 0,
      "total": 0
    },
    "total": {
      "4": 119,
      "3": 401
    },
    "3": {
      "4": 119,
      "3": 401,
      "total": 520
    }
  },
  "realMonths": [
    "2023-03"
  ]
}
```

</details>



Detailed API description will come later.
