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


### Second method: App Password (UI)
This second method gives a larger access to your account, not just to cospend or to a single cospend's project.

#### Obtaining the credentials
First of all, you need a **password**. Open your Nextcloud UI, visit your personal settings, under Security (https://mynextcloud.org/settings/user/security). Under _Devices & Sessions_, create a new app password. Make good note of the given password (a series of alphanumericals separated by dashes). This is your `<app_password>`.

The next bit of information is your Nextcloud's **username** (used to login, not your profile name neither your email address). This is your `<username>`

To work on a project, you will also need its **ID**. Note that this is different than the `<project_token>` used earlier. Here, we don't need to create a guest access to a project. The project ID is the name of the project, in lower case, with spaces replaced by dashes. When you click on the project in the web UI, you will see the ID in the address bar: `https://mynextcloud.org/apps/cospend/p/<project_id>`

If you use this method, your API path will have the following shape: `<root_url>/api-priv/projects/<project_id>/<command>`

**Do note:**
* This URL starts with `api-priv` instead of `api`. This is for logged in clients, that don't require a project's password.
* There's no more password on the project
#### Using the credentials
This method uses [HTTP Basic Authentication](https://developer.mozilla.org/en-US/docs/Web/HTTP/Authentication#basic_authentication_scheme). Most libraries and languages offer an easy method for that (e.g. `curl -u "<username>:<app_password>" <url>`). If you need to do it manually:
* Prepare the string
* Base64 encode it
* Pass the result to a header `Authorization: Basic `

For instance:
```bash
curl -u "johndoe:mypassword" https://example.com
# equivalent to:
encoded=$(echo -n "johndoe:mypassword" | base64) # -n mandatory, otherwise \n is also encoded
curl -H "Authorization: Basic $encoded" https://example.com
```

In order for the header to be correctly interpreted, you also need an additional header: `OCS-APIRequest: true`. For instance:
```
curl -H "OCS-APIRequest: true" -u "<username>:<app_password>" <url>
```
#### Trying
You can now try your first `<command>`: `/statistics`. If you've followed correctly, your request should look like this:

```bash
curl -s -u "<username>:<app_password" -H "OCS-APIRequest: true" \
https://mynextcloud.org/index.php/apps/cospend/api/projects/<project_id>/statistics
```
And obtain the same result as before.



## Specifications
Now that you've managed to land your first succesful request, let's dive into the concrete API specification.

As explained above, depending on your authentication method, the endpoint will look slightly different.
The core part of the endpoint (`/api/projects/<project_token>/<project_password>` for anonymous, and `/api-priv/projects/<project_id>` for logged in) will be substituted by `<base_endpoint>`. If `<base_endpoint>` is not mentioned, the endpoint is relative to the root url.

**Examples**:
|        Endpoint         | Full URL(s)                                                                                                                                                                                         |
| :---------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|       `/api/ping`       | `https://mynextcloud.org/index.php/apps/cospend/api/ping`                                                                                                                                           |
| `<base_endpoint>/bills` | *`https://mynextcloud.org/index.php/apps/cospend/api/projects/<project_token>/<project_password>/bills` <br> *`https://mynextcloud.org/index.php/apps/cospend/api-priv/projects/<project_id>/bills` |


| Name                                               | Endpoint                                                      | Method | Anonymous/Logged |
| -------------------------------------------------- | :------------------------------------------------------------ | :----- | ---------------- |
| [Ping                  ](#ping                   ) | `/api/ping`                                                   | GET    | **Logged**       |
| [Create Project        ](#create-project         ) | `/api/projects` (anonymous),<br>`/api-priv/projects` (logged) | POST   | Anonymous/Logged |
| [Get Project Info      ](#get-project-info       ) | `<base_endpoint>/`                                            | GET    | Anonymous/Logged |
| [Set Project Info      ](#set-project-info       ) | `<base_endpoint>/`                                            | PUT    | Anonymous/Logged |
| [Delete project        ](#delete-project         ) | `<base_endpoint>/`                                            | DELETE | Anonymous/Logged |
| [Get Members           ](#get-members            ) | `<base_endpoint>/members`                                     | GET    | Anonymous/Logged |
| [Add Member            ](#add-member             ) | `<base_endpoint>/members`                                     | POST   | Anonymous/Logged |
| [Add Member V2         ](#add-member-v2          ) | `/apiv2/projects/<project_token>/<project_password>/members`  | POST   | **Anonymous**    |
| [Edit Member           ](#edit-member            ) | `<base_endpoint>/members/<member_id>`                         | PUT    | Anonymous/Logged |
| [Delete Member         ](#delete-member          ) | `<base_endpoint>/members/<member_id>`                         | DELETE | Anonymous/Logged |
| [Get Bills             ](#get-bills              ) | `<base_endpoint>/bills`                                       | GET    | Anonymous/Logged |
| [Get Bills V2          ](#get-bills-v2           ) | `/apiv2/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Get Bills V3          ](#get-bills-v3           ) | `/apiv3/projects/<project_token>/<project_password>/bills`    | GET    | **Anonymous**    |
| [Add Bill              ](#add-bill               ) | `<base_endpoint>/bills`                                       | POST   | Anonymous/Logged |
| [Edit Bill             ](#edit-bill              ) | `<base_endpoint>/bills/<bill_id>`                             | PUT    | Anonymous/Logged |
| [Delete Bill           ](#delete-bill            ) | `<base_endpoint>/bills/<bill_id>`                             | DELETE | Anonymous/Logged |
| [Get Project Statistics](#get-project-statistics ) | `<base_endpoint>/statistics`                                  | GET    | Anonymous/Logged |
| [Get Project Settlement](#get-project-settlement ) | `<base_endpoint>/settle`                                      | GET    | Anonymous/Logged |
| [Auto Settlement       ](#auto-settlement        ) | `<base_endpoint>/autosettlement`                              | GET    | Anonymous/Logged |
| [Add Currency          ](#add-currency           ) | `<base_endpoint>/currency`                                    | POST   | Anonymous/Logged |
| [Edit Currency         ](#edit-currency          ) | `<base_endpoint>/currency/<currency_id>`                      | PUT    | Anonymous/Logged |
| [Delete Currency       ](#delete-currency        ) | `<base_endpoint>/currency/<currency_id>`                      | DELETE | Anonymous/Logged |
| [Add Payment Mode      ](#add-payment-mode       ) | `<base_endpoint>/paymentmode`                                 | POST   | Anonymous/Logged |
| [Edit Payment Mode     ](#edit-payment-mode      ) | `<base_endpoint>/paymentmode/<pm_id>`                         | PUT    | Anonymous/Logged |
| [Delete Payment Mode   ](#delete-payment-mode    ) | `<base_endpoint>/paymentmode/<pm_id>`                         | DELETE | Anonymous/Logged |
| [Add Category          ](#add-category           ) | `<base_endpoint>/category/`                                   | POST   | Anonymous/Logged |
| [Edit Category         ](#edit-category          ) | `<base_endpoint>/category/<category_id>`                      | PUT    | Anonymous/Logged |
| [Delete Category       ](#delete-category        ) | `<base_endpoint>/category/<category_id>`                      | DELETE | Anonymous/Logged |

