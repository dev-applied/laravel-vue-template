// lib/server-stack.ts
import * as cdk from 'aws-cdk-lib'
import {Construct} from 'constructs'
import * as ec2 from 'aws-cdk-lib/aws-ec2'
import * as rds from 'aws-cdk-lib/aws-rds'
import * as fs from 'fs'

const whiteListedIps: string[] = []

export class DevServerStack extends cdk.Stack {

  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props)

    // VPC
    const vpc = new ec2.Vpc(this, 'Vpc', {
      maxAzs: 2,
    })

    // Security Group for EC2
    const ec2Sg = new ec2.SecurityGroup(this, 'Ec2SecurityGroup', {
      vpc,
      description: 'Allow SSH and DB access',
      allowAllOutbound: true,
    })
    whiteListedIps.forEach(ip => {
      ec2Sg.addIngressRule(ec2.Peer.ipv4(ip), ec2.Port.tcp(22), `Allow SSH from ${ip}`)
    })


    // Security Group for DB
    const dbSg = new ec2.SecurityGroup(this, 'DbSecurityGroup', {
      vpc,
      description: 'Allow DB access from EC2',
      allowAllOutbound: true,
    })
    dbSg.addIngressRule(ec2Sg, ec2.Port.tcp(3306), 'Allow MySQL from EC2')

    // Reference existing key pair (replace with your key name)
    const keyPair = ec2.KeyPair.fromKeyPairName(this, 'KeyPair', 'your-keypair-name')

    // Read new-server.sh contents and adapt for non-interactive user data
    let userDataScript = fs.readFileSync(__dirname + '/../new-server.sh', 'utf8')
    // Remove interactive prompt and set default PHP version
    userDataScript = userDataScript.replace(/while[\s\S]*?done\n/, 'phpversion="8.3"\n')

    // EC2 Instance
    const instance = new ec2.Instance(this, 'Instance', {
      vpc,
      instanceType: ec2.InstanceType.of(ec2.InstanceClass.T3, ec2.InstanceSize.MICRO),
      machineImage: ec2.MachineImage.latestAmazonLinux2023(),
      securityGroup: ec2Sg,
      keyPair,
      userData: ec2.UserData.custom(userDataScript),
    })

    const eip = new ec2.CfnEIP(this, 'EIP', {
      instanceId: instance.instanceId,
    })

    const db = new rds.DatabaseInstance(this, 'Database', {
      engine: rds.DatabaseInstanceEngine.mysql({version: rds.MysqlEngineVersion.VER_8_0}),
      vpc,
      vpcSubnets: {subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS},
      instanceType: ec2.InstanceType.of(ec2.InstanceClass.T3, ec2.InstanceSize.MICRO),
      securityGroups: [dbSg],
      multiAz: false,
      allocatedStorage: 20,
      storageType: rds.StorageType.GP2,
      credentials: rds.Credentials.fromGeneratedSecret('admin'),
      databaseName: 'mydb',
      publiclyAccessible: false,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    })
  }
}
